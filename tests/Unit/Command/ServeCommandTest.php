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

namespace Crest\Tests\Unit\Command;

use Crest\Command\NewCommand;
use Crest\Command\ServeCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Project\Locator;
use Crest\Tests\Support\Process\FakeRunner;
use Crest\Tests\Support\RunsACommandDirectly;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function array_slice;
use function chdir;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function json_decode;
use function mkdir;
use function preg_match;
use function putenv;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

final class ServeCommandTest extends TestCase
{
    use RunsACommandDirectly;
    use ScratchDirectory;

    private string $previousCwd = '';

    protected function setUp(): void
    {
        $this->captureStreams();

        // The scratch project has the router, vendor/autoload.php and src/,
        // but no public/. If a real server starts by mistake, PHP stops at
        // once with "Directory public does not exist". Thus no test can hang.
        $this->makeScratchDirectory('serve', 'src');
        $this->project($this->root);

        // An APP_PORT from outside the suite must not change the port.
        putenv('APP_PORT');

        $this->previousCwd = (string) getcwd();
    }

    protected function tearDown(): void
    {
        putenv('APP_PORT');
        chdir($this->previousCwd);

        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function envFiles(): iterable
    {
        yield 'the generated file' => ["PROJECT_PREFIX=my-app\nAPP_PORT=9000\nUID=1000\n", '127.0.0.1:9000'];
        yield 'double quotes' => ["APP_PORT=\"9000\"\n", '127.0.0.1:9000'];
        yield 'single quotes' => ["APP_PORT='9000'\n", '127.0.0.1:9000'];
        yield 'spaces' => ["  APP_PORT = 9000  \n", '127.0.0.1:9000'];
        yield 'Windows line ends' => ["APP_PORT=9000\r\nUID=1000\r\n", '127.0.0.1:9000'];
        // docker compose uses the last line.
        yield 'two lines' => ["APP_PORT=9000\nAPP_PORT=9001\n", '127.0.0.1:9001'];
        yield 'a comment first' => ["# APP_PORT=9001\nAPP_PORT=9000\n", '127.0.0.1:9000'];
        yield 'a longer name first' => ["MY_APP_PORT=9001\nAPP_PORT=9000\n", '127.0.0.1:9000'];
        yield 'export' => ["export APP_PORT=9000\n", '127.0.0.1:9000'];
        yield 'a colon' => ["APP_PORT: 9000\n", '127.0.0.1:9000'];
        yield 'a comment after the value' => ["APP_PORT=9000 # web\n", '127.0.0.1:9000'];
        yield 'a comment after quotes' => ["APP_PORT=\"9000\" # web\n", '127.0.0.1:9000'];
        // Empty reads as absent, as ${APP_PORT:-8080} in docker-compose.yml.
        yield 'empty' => ["APP_PORT=\nUID=1000\n", '127.0.0.1:8080'];
        yield 'empty quotes' => ["APP_PORT=\"\"\n", '127.0.0.1:8080'];
        yield 'empty on the last line' => ["APP_PORT=9000\nAPP_PORT=\n", '127.0.0.1:8080'];
        yield 'no APP_PORT' => ["UID=1000\n", '127.0.0.1:8080'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPorts(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['abc'];
        yield 'zero' => ['0'];
        yield 'above the range' => ['65536'];
        yield 'negative' => ['-1'];
        yield 'text after the digits' => ['8080x'];
        yield 'text before the digits' => ['x8080'];
        // PHP casts '+8080' to 8080. Only the pattern stops it.
        yield 'a sign before the digits' => ['+8080'];
        yield 'a trailing newline' => ["8080\n"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validPorts(): iterable
    {
        yield 'the lowest' => ['1', '127.0.0.1:1'];
        yield 'the highest' => ['65535', '127.0.0.1:65535'];
        yield 'another' => ['9000', '127.0.0.1:9000'];
        yield 'leading zeros' => ['0080', '127.0.0.1:80'];
    }

    public function testAMissingAutoloaderStopsBeforePhpStarts(): void
    {
        // composer install has not run.
        file_put_contents($this->root . '/src/' . ServeCommand::ROUTER, "<?php\n");

        $this->assertSame(
            $this->root . '/src/vendor/autoload.php was not found; run composer install first',
            $this->refusal(['--directory', $this->root . '/src'])
        );
    }

    public function testAMissingRouterStopsBeforePhpStarts(): void
    {
        // A project that `new` did not make, or a wrong --directory.
        $this->assertSame(
            $this->root . '/src/.htrouter.php was not found; serve uses the router script that crest new writes',
            $this->refusal(['--directory', $this->root . '/src'])
        );
    }

    public function testAnEmptyAppPortInTheEnvironmentReadsAsAbsent(): void
    {
        // As ${APP_PORT:-8080} in docker-compose.yml.
        putenv('APP_PORT=');
        file_put_contents($this->root . '/.env', "APP_PORT=9000\n");

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--directory', $this->root]);

        $this->assertSame([[$this->argv('127.0.0.1:9000'), $this->root]], $runner->calls);
    }

    public function testAnEmptyDirectoryOptionMeansTheWorkingDirectory(): void
    {
        // `--directory="$DIR"` with an unset variable. As for `new` and `up`,
        // empty reads as absent.
        chdir($this->root);

        // The test needs no crest.php here or above.
        $this->assertNull(Locator::locate($this->root));

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--directory=']);

        $this->assertSame([[$this->argv('127.0.0.1:8080'), $this->root]], $runner->calls);
    }

    public function testAnInvalidAppPortInTheEnvFileStopsBeforePhpStarts(): void
    {
        file_put_contents($this->root . '/.env', "APP_PORT=abc\n");

        $this->assertSame(
            "'abc' is not a port (APP_PORT in " . $this->root . "/.env); expected an integer from 1 to 65535",
            $this->refusal(['--directory', $this->root])
        );
    }

    public function testAnInvalidAppPortInTheEnvironmentStopsBeforePhpStarts(): void
    {
        putenv('APP_PORT=abc');

        $this->assertSame(
            "'abc' is not a port (APP_PORT in the environment); expected an integer from 1 to 65535",
            $this->refusal(['--directory', $this->root])
        );
    }

    /**
     * @dataProvider invalidPorts
     */
    public function testAnInvalidPortStopsBeforePhpStarts(string $port): void
    {
        $this->assertSame(
            "'" . $port . "' is not a port; expected an integer from 1 to 65535",
            $this->refusal(['--port=' . $port, '--directory', $this->root])
        );
    }

    public function testATrailingSlashOnTheDirectoryIsRemoved(): void
    {
        // Shell completion adds the slash. The root is the same directory.
        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--directory', $this->root . '/']);

        $this->assertSame([[$this->argv('127.0.0.1:8080'), $this->root]], $runner->calls);
    }

    /**
     * @dataProvider validPorts
     */
    public function testAValidPortReachesTheServer(string $port, string $address): void
    {
        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--port=' . $port, '--directory', $this->root]);

        $this->assertSame([[$this->argv($address), $this->root]], $runner->calls);
    }

    public function testAVariableInTheEnvFileStopsBeforePhpStarts(): void
    {
        // docker compose expands ${WEB_PORT}. serve does not. It stops, and it
        // does not use a different port.
        file_put_contents($this->root . '/.env', "APP_PORT=\${WEB_PORT}\n");

        $this->assertSame(
            "'\${WEB_PORT}' is not a port (APP_PORT in " . $this->root . "/.env); expected an integer from 1 to 65535",
            $this->refusal(['--directory', $this->root])
        );
    }

    public function testServeAgreesWithTheGeneratedContainer(): void
    {
        // serve and `up` must run the same application on the same port. new
        // writes the Dockerfile and docker-compose.yml. public/ is removed, for
        // the reason in setUp().
        $this->handleDirectly(new NewCommand(), ['app', '--directory', $this->root]);

        $project = $this->root . '/app';

        mkdir($project . '/vendor');
        file_put_contents($project . '/vendor/autoload.php', "<?php\n");
        $this->safeDeleteDirectory($project . '/public');

        // CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", ".htrouter.php"]
        preg_match('/^CMD (\[.+\])$/m', (string) file_get_contents($project . '/resources/docker/Dockerfile'), $cmd);

        // "${APP_PORT:-8080}:8080": the variable and its default.
        preg_match('/"\$\{(\w+):-(\d+)\}:\d+"/', (string) file_get_contents($project . '/docker-compose.yml'), $port);

        /** @var list<string> $arguments */
        $arguments = json_decode($cmd[1] ?? '', true, 512, JSON_THROW_ON_ERROR);

        // The arguments after the address: the document root and the router.
        $tail     = array_slice($arguments, 3);
        $variable = $port[1] ?? '';
        $runner   = new FakeRunner();

        file_put_contents($project . '/.env', $variable . "=9124\n");
        $this->handleDirectly(new ServeCommand($runner), ['--directory', $project]);

        unlink($project . '/.env');
        $this->handleDirectly(new ServeCommand($runner), ['--directory', $project]);

        putenv($variable . '=9123');
        $this->handleDirectly(new ServeCommand($runner), ['--directory', $project]);

        $this->assertSame(
            [
                [[PHP_BINARY, '-S', '127.0.0.1:9124', ...$tail], $project],
                [[PHP_BINARY, '-S', '127.0.0.1:' . ($port[2] ?? ''), ...$tail], $project],
                [[PHP_BINARY, '-S', '127.0.0.1:9123', ...$tail], $project],
            ],
            $runner->calls
        );
    }

    public function testTheDefaultPortIs8080(): void
    {
        $runner = new FakeRunner();

        $status = $this->handleDirectly(new ServeCommand($runner), ['--directory', $this->root]);

        $this->assertSame(0, $status);
        $this->assertSame([[$this->argv('127.0.0.1:8080'), $this->root]], $runner->calls);
    }

    public function testTheDirectoryOptionWinsOverCrestPhp(): void
    {
        // Run from a project root that has crest.php. --directory names
        // another project, and serve runs there.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn [];\n");
        $this->project($this->root . '/src');
        chdir($this->root);

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--directory', $this->root . '/src']);

        $this->assertSame([[$this->argv('127.0.0.1:8080'), $this->root . '/src']], $runner->calls);
    }

    /**
     * @dataProvider envFiles
     */
    public function testTheEnvFileCanSetThePort(string $contents, string $address): void
    {
        // docker-compose.yml publishes APP_PORT from .env. serve uses the
        // same port.
        file_put_contents($this->root . '/.env', $contents);

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--directory', $this->root]);

        $this->assertSame([[$this->argv($address), $this->root]], $runner->calls);
    }

    public function testTheEnvironmentComesBeforeTheEnvFile(): void
    {
        // The same order as docker compose.
        putenv('APP_PORT=9001');
        file_put_contents($this->root . '/.env', "APP_PORT=9002\n");

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--directory', $this->root]);

        $this->assertSame([[$this->argv('127.0.0.1:9001'), $this->root]], $runner->calls);
    }

    public function testTheExitStatusOfTheServerIsReturned(): void
    {
        // For example, PHP exits with 1 when the port is in use.
        $this->assertSame(
            3,
            $this->handleDirectly(new ServeCommand(new FakeRunner(3)), ['--directory', $this->root])
        );
    }

    public function testThePortOptionComesBeforeAppPort(): void
    {
        putenv('APP_PORT=9001');
        file_put_contents($this->root . '/.env', "APP_PORT=9002\n");

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), ['--port=9003', '--directory', $this->root]);

        $this->assertSame([[$this->argv('127.0.0.1:9003'), $this->root]], $runner->calls);
    }

    public function testTheRootIsTheNearestCrestPhpAbove(): void
    {
        // Run from src/. `up` also works from a subdirectory.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn [];\n");
        chdir($this->root . '/src');

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), []);

        $this->assertSame([[$this->argv('127.0.0.1:8080'), $this->root]], $runner->calls);
    }

    public function testWithoutCrestPhpTheWorkingDirectoryIsTheRoot(): void
    {
        // No crest.php here or above: this repository has no crest.php above
        // tests/_output.
        chdir($this->root);

        $this->assertNull(Locator::locate($this->root));

        $runner = new FakeRunner();

        $this->handleDirectly(new ServeCommand($runner), []);

        $this->assertSame([[$this->argv('127.0.0.1:8080'), $this->root]], $runner->calls);
    }

    /**
     * The argv that serve gives to the runner.
     *
     * @return list<string>
     */
    private function argv(string $address): array
    {
        return [PHP_BINARY, '-S', $address, '-t', 'public', '.htrouter.php'];
    }

    /**
     * Writes the two files that serve checks in a project: the router and
     * the composer autoloader.
     */
    private function project(string $directory): void
    {
        mkdir($directory . '/vendor');
        file_put_contents($directory . '/' . ServeCommand::ROUTER, "<?php\n");
        file_put_contents($directory . '/vendor/autoload.php', "<?php\n");
    }

    /**
     * Runs serve with a fake runner, and returns the message of the crest
     * error. The runner must have no calls: serve checks its input before
     * PHP starts.
     *
     * @param list<string> $tokens
     */
    private function refusal(array $tokens): string
    {
        $runner = new FakeRunner();

        try {
            $this->handleDirectly(new ServeCommand($runner), $tokens);
        } catch (Exception $exception) {
            $this->assertSame([], $runner->calls);

            return $exception->getMessage();
        }

        $this->fail('serve did not stop');
    }
}
