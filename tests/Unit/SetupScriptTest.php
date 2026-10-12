<?php

/**
 * This file is part of the Phalcon Crest.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Crest\Tests\Unit;

use Crest\Paths;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_slice;
use function chmod;
use function copy;
use function fclose;
use function file;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function fwrite;
use function mkdir;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function strtr;

use const FILE_IGNORE_NEW_LINES;
use const PHP_BINARY;
use const PHP_EOL;

/**
 * Runs resources/setup.sh as `curl | sh` does: the script on stdin of sh -s,
 * in the scratch root. Fakes of docker, composer, id and chown are first on
 * the PATH. The fake docker runs the sh -c part of `docker run` here, so the
 * real `crest new` of this repository makes the project. Then the launcher of
 * the project calls the fake docker for up and install.
 */
final class SetupScriptTest extends TestCase
{
    use ScratchDirectory;

    /**
     * Writes its arguments to the log. A user that is not root cannot give
     * files to 1234.
     */
    private const CHOWN = <<<'SH'
        #!/bin/sh

        printf '%s\n' "$*" >> '{{ log }}/chown.log'
        SH;

    /**
     * Writes its arguments to the log. Then it installs this crest into
     * COMPOSER_HOME, as `composer global require` does.
     */
    private const COMPOSER = <<<'SH'
        #!/bin/sh

        printf '%s\n' "$*" >> '{{ log }}/composer.log'
        mkdir -p "$COMPOSER_HOME/vendor/bin"
        printf '#!/bin/sh\n\nexec %s %s "$@"\n' '{{ php }}' '{{ crest }}' > "$COMPOSER_HOME/vendor/bin/crest"
        chmod 0755 "$COMPOSER_HOME/vendor/bin/crest"
        SH;

    /**
     * Writes each call to the log, on one line: the working folder, then the
     * arguments. The script body after -c shows as <script>. With
     * FAIL=<word>, a call with an argument equal to <word> exits with status
     * 3. For `run`, the value after -v goes to its own log, so that a value
     * in two parts shows. Then the part after the image runs here, as in the
     * container.
     */
    private const DOCKER = <<<'SH'
        #!/bin/sh

        line="$(pwd):"
        previous=
        for argument in "$@"; do
            if [ "$previous" = -c ]; then
                line="$line <script>"
            else
                line="$line $argument"
            fi
            previous=$argument
        done
        printf '%s\n' "$line" >> '{{ log }}/docker.log'

        for argument in "$@"; do
            if [ -n "${FAIL:-}" ] && [ "$argument" = "$FAIL" ]; then
                exit 3
            fi
        done

        if [ "$1" = run ]; then
            while [ "$1" != composer:2 ]; do
                if [ "$1" = -v ]; then
                    printf '%s\n' "$2" >> '{{ log }}/volume.log'
                fi
                shift
            done
            shift
            COMPOSER_HOME='{{ log }}/composer' exec "$@"
        fi

        exit 0
        SH;

    /**
     * The host user is 1234:5678. The test user is 1000, which is also the
     * default in .env, so the real id would not show that the script writes
     * the IDs.
     */
    private const ID = <<<'SH'
        #!/bin/sh

        case "$1" in
            -u) echo 1234 ;;
            -g) echo 5678 ;;
        esac
        SH;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('setup', 'bin', 'empty');

        $fakes = [
            'chown'    => self::CHOWN,
            'composer' => self::COMPOSER,
            'docker'   => self::DOCKER,
            'id'       => self::ID,
        ];

        foreach ($fakes as $name => $script) {
            file_put_contents(
                $this->root . '/bin/' . $name,
                strtr(
                    $script,
                    [
                        '{{ crest }}' => Paths::root() . '/bin/crest',
                        '{{ log }}'   => $this->root,
                        '{{ php }}'   => PHP_BINARY,
                    ]
                ) . "\n"
            );
            chmod($this->root . '/bin/' . $name, 0o755);
        }
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function directories(): iterable
    {
        yield 'a separate value' => [['my-app', '--directory', 'elsewhere']];
        yield 'an equals sign' => [['my-app', '--directory=elsewhere']];
    }

    public function testAFailedInstallStopsTheSetup(): void
    {
        [$status, $stdout] = $this->runSetup(['my-app'], ['FAIL' => 'exec']);

        $this->assertSame(3, $status);
        $this->assertStringContainsString('crest: installing my-app' . PHP_EOL, $stdout);
        $this->assertStringNotContainsString('runs on', $stdout);
    }

    public function testAFailedUpStopsTheSetup(): void
    {
        [$status, $stdout] = $this->runSetup(['my-app'], ['FAIL' => 'up']);

        $this->assertSame(3, $status);
        $this->assertSame(
            [$this->root . '/my-app: compose up -d'],
            array_slice($this->log('docker'), 2)
        );
        $this->assertStringNotContainsString('installing', $stdout);
        $this->assertStringNotContainsString('runs on', $stdout);
    }

    public function testAnExportedCdpathDoesNotMoveTheSetup(): void
    {
        // With CDPATH, `cd my-app` can go to another folder with the same
        // name, and cd then prints that folder.
        mkdir($this->root . '/elsewhere/my-app', 0o775, true);

        [$status, $stdout] = $this->runSetup(['my-app'], ['CDPATH' => $this->root . '/elsewhere']);

        $this->assertSame(0, $status);
        $this->assertSame(
            [
                $this->root . '/my-app: compose up -d',
                $this->root . '/my-app: compose exec -T app composer install',
            ],
            array_slice($this->log('docker'), 2)
        );
        $this->assertStringNotContainsString('elsewhere', $stdout);
    }

    public function testAnOptionFirstGivesTheUsage(): void
    {
        [$status, $stdout, $stderr] = $this->runSetup(['--phalcon', 'v6', 'my-app']);

        $this->assertSame(1, $status);
        $this->assertSame('', $stdout);
        $this->assertSame('usage: setup.sh <name> [options of crest new]' . PHP_EOL, $stderr);
        $this->assertSame([], $this->log('docker'));
    }

    public function testAWorkingFolderWithASpaceIsNoProblem(): void
    {
        $folder = $this->root . '/my projects';
        mkdir($folder);

        [$status] = $this->runSetup(['my-app'], [], $folder);

        $this->assertSame(0, $status);
        // One argument: the log of the calls does not show a value in two parts.
        $this->assertSame([$folder . ':/app'], $this->log('volume'));
        $this->assertSame(
            [
                $folder . ': compose version',
                $folder . ': run --rm -v ' . $folder . ':/app -w /app -e COMPOSER_HOME=/composer composer:2'
                . ' sh -c <script> setup dev-master 1234 5678 my-app',
                $folder . '/my-app: compose up -d',
                $folder . '/my-app: compose exec -T app composer install',
            ],
            $this->log('docker')
        );
    }

    public function testBashRunsTheSetup(): void
    {
        // /bin/sh is bash on Fedora and macOS.
        [$status, $stdout] = $this->runSetup(['my-app'], [], null, ['/bin/bash', '-s', '--']);

        $this->assertSame(0, $status);
        $this->assertCount(4, $this->log('docker'));
        $this->assertStringContainsString('crest: my-app runs on http://localhost:8080/' . PHP_EOL, $stdout);
    }

    /**
     * @dataProvider directories
     *
     * @param list<string> $arguments
     */
    public function testDirectoryIsRefused(array $arguments): void
    {
        // Only the working folder is mounted in the one-off container.
        [$status, $stdout, $stderr] = $this->runSetup($arguments);

        $this->assertSame(1, $status);
        $this->assertSame('', $stdout);
        $this->assertSame(
            'crest: --directory is not supported; the project goes into the working folder' . PHP_EOL,
            $stderr
        );
        $this->assertSame([], $this->log('docker'));
    }

    public function testNewRefusesAFolderThatIsNotEmpty(): void
    {
        mkdir($this->root . '/my-app');
        file_put_contents($this->root . '/my-app/notes.txt', '');

        [$status, $stdout, $stderr] = $this->runSetup(['my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('exists and is not empty', $stderr);
        $this->assertStoppedAfterNew($stdout);
    }

    public function testNewRefusesAPathAsTheName(): void
    {
        // new checks the name before sed and chown use it.
        [$status, $stdout, $stderr] = $this->runSetup(['../x']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('is not a usable project name', $stderr);
        $this->assertStoppedAfterNew($stdout);
    }

    public function testNoArgumentGivesTheUsage(): void
    {
        [$status, $stdout, $stderr] = $this->runSetup([]);

        $this->assertSame(1, $status);
        $this->assertSame('', $stdout);
        $this->assertSame('usage: setup.sh <name> [options of crest new]' . PHP_EOL, $stderr);
        $this->assertSame([], $this->log('docker'));
    }

    public function testNoComposeStopsTheSetup(): void
    {
        [$status, $stdout, $stderr] = $this->runSetup(['my-app'], ['FAIL' => 'version']);

        $this->assertSame(1, $status);
        $this->assertSame('', $stdout);
        $this->assertSame('crest: docker compose is not installed' . PHP_EOL, $stderr);
        $this->assertSame([$this->root . ': compose version'], $this->log('docker'));
    }

    public function testNoDockerStopsTheSetup(): void
    {
        // Before this check, the script uses only shell built-ins.
        [$status, $stdout, $stderr] = $this->runSetup(['my-app'], ['PATH' => $this->root . '/empty']);

        $this->assertSame(1, $status);
        $this->assertSame('', $stdout);
        $this->assertSame('crest: docker is not installed' . PHP_EOL, $stderr);
    }

    public function testTheDownloadedScriptRunsWithSh(): void
    {
        // The documented way to read the script before it runs.
        copy(Paths::root() . '/resources/setup.sh', $this->root . '/crest-setup.sh');

        [$status, $stdout] = $this->runSetup(['my-app'], [], null, ['/bin/sh', 'crest-setup.sh']);

        $this->assertSame(0, $status);
        $this->assertCount(4, $this->log('docker'));
        $this->assertStringContainsString('crest: my-app runs on http://localhost:8080/' . PHP_EOL, $stdout);
    }

    public function testThePortComesFromTheEnvironment(): void
    {
        [$status, $stdout] = $this->runSetup(['my-app'], ['APP_PORT' => '9000']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('crest: my-app runs on http://localhost:9000/' . PHP_EOL, $stdout);
    }

    public function testTheProjectGetsTheHostIdsAndTheOptions(): void
    {
        [$status] = $this->runSetup(['my-app', '--phalcon', 'v6']);

        $this->assertSame(0, $status);

        $env = (string) file_get_contents($this->root . '/my-app/.env');

        $this->assertMatchesRegularExpression('/^UID=1234$/m', $env);
        $this->assertMatchesRegularExpression('/^GID=5678$/m', $env);
        $this->assertStringContainsString(
            '"phalcon/phalcon"',
            (string) file_get_contents($this->root . '/my-app/composer.json')
        );
        $this->assertSame(0o755, fileperms($this->root . '/my-app/crest') & 0o777);
    }

    public function testTheServiceGoesThroughNewToTheLauncher(): void
    {
        [$status] = $this->runSetup(['my-app', '--service', 'web']);

        $this->assertSame(0, $status);
        $this->assertSame(
            [
                $this->root . '/my-app: compose up -d',
                $this->root . '/my-app: compose exec -T web composer install',
            ],
            array_slice($this->log('docker'), 2)
        );
    }

    public function testTheSetupPrintsItsStepsAndTheUrl(): void
    {
        // The report of new does not show: the script runs the next steps.
        [$status, $stdout] = $this->runSetup(['my-app']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'crest: creating my-app' . PHP_EOL
            . 'crest: starting my-app' . PHP_EOL
            . 'crest: installing my-app' . PHP_EOL
            . 'crest: my-app runs on http://localhost:8080/' . PHP_EOL
            . 'Next:' . PHP_EOL
            . '    cd my-app' . PHP_EOL
            . '    ./crest make:action GET /hello' . PHP_EOL,
            $stdout
        );
    }

    public function testTheSetupRunsNewThenUpThenInstall(): void
    {
        [$status] = $this->runSetup(['my-app', '--phalcon', 'v6']);

        $this->assertSame(0, $status);
        $this->assertSame(
            [
                $this->root . ': compose version',
                $this->root . ': run --rm -v ' . $this->root . ':/app -w /app -e COMPOSER_HOME=/composer composer:2'
                . ' sh -c <script> setup dev-master 1234 5678 my-app --phalcon v6',
                $this->root . '/my-app: compose up -d',
                $this->root . '/my-app: compose exec -T app composer install',
            ],
            $this->log('docker')
        );
        $this->assertSame(['global require --no-interaction phalcon/crest:dev-master'], $this->log('composer'));
        $this->assertSame(['-R 1234:5678 my-app'], $this->log('chown'));
    }

    /**
     * new refused: chown, up and install did not run.
     */
    private function assertStoppedAfterNew(string $stdout): void
    {
        $this->assertCount(2, $this->log('docker'));
        $this->assertSame([], $this->log('chown'));
        $this->assertStringNotContainsString('starting', $stdout);
    }

    /**
     * @return list<string> The lines of the log of a fake. None when the fake
     *                      did not run.
     */
    private function log(string $fake): array
    {
        $file = $this->root . '/' . $fake . '.log';

        if (false === file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        return false === $lines ? [] : $lines;
    }

    /**
     * Runs the setup script. By default as `curl | sh` does: the script on
     * stdin of `sh -s --`, in the scratch root.
     *
     * @param list<string>          $arguments
     * @param array<string, string> $environment More variables. A PATH here
     *                                           replaces the PATH of the fakes.
     * @param string|null           $folder      The working folder. Null for the
     *                                           scratch root.
     * @param list<string>          $shell       The shell and its options. With
     *                                           `-s`, the script goes to stdin.
     *
     * @return array{int, string, string} The exit status, stdout and stderr.
     */
    private function runSetup(
        array $arguments,
        array $environment = [],
        ?string $folder = null,
        array $shell = ['/bin/sh', '-s', '--']
    ): array {
        $process = proc_open(
            [...$shell, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $folder ?? $this->root,
            ['PATH' => $this->root . '/bin:/usr/bin:/bin', ...$environment]
        );

        if (false === $process) {
            throw new RuntimeException('could not start the shell');
        }

        fwrite($pipes[0], (string) file_get_contents(Paths::root() . '/resources/setup.sh'));
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
