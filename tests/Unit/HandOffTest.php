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

use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Output;
use Crest\Console\Parsing\Option;
use Crest\HandOff;
use Crest\Process\ShellRunner;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\Process\FakeRunner;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function array_map;
use function chdir;
use function file_put_contents;
use function getcwd;
use function getenv;
use function json_encode;
use function putenv;
use function unlink;

use const PHP_BINARY;
use const PHP_EOL;

/**
 * The scratch root has three directories: app/ is a project with crest.php
 * that requires crest and has vendor/bin/crest, other/ is not a project, and
 * self/ is the vendor folder of the running crest.
 *
 * A walk up from other/ finds no crest.php: this repository has none.
 */
final class HandOffTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    private string $previousCwd = '';

    private FakeRunner $runner;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('hand-off', 'app/src/Action', 'app/vendor/bin', 'other', 'self');
        $this->captureStreams();
        $this->requiring('require-dev');

        file_put_contents($this->root . '/app/crest.php', "<?php\n\nreturn [];\n");

        file_put_contents($this->root . '/app/vendor/bin/crest', "<?php\n");

        $this->runner = new FakeRunner();

        // Most calls start in the project, as the calls of a user who works
        // in it.
        $this->previousCwd = (string) getcwd();
        chdir($this->root . '/app');
    }

    protected function tearDown(): void
    {
        putenv(HandOff::VARIABLE);
        chdir($this->previousCwd);

        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostCommands(): iterable
    {
        yield 'down' => ['down'];
        yield 'init' => ['init'];
        yield 'install' => ['install'];
        yield 'new' => ['new'];
        yield 'up' => ['up'];
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function listings(): iterable
    {
        yield 'no command' => [[]];
        yield 'an option first' => [['--help']];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requirements(): iterable
    {
        yield 'require' => ['require'];
        yield 'require-dev' => ['require-dev'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function serveCommands(): iterable
    {
        yield 'serve' => ['serve'];
        yield 'the alias' => ['server'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function versionFlags(): iterable
    {
        yield 'long' => ['--version'];
        yield 'short' => ['-V'];
    }

    public function testABrokenCrestPhpIsReported(): void
    {
        // One crest line, not a PHP trace.
        $this->runtime("['type' => 'podman']");

        $this->assertSame(1, $this->handOff(['route:list']));
        $this->assertSame([], $this->runner->calls);
        $this->assertSame(
            "crest: unknown runtime 'podman'; expected host or docker" . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testACallFromASubdirectoryIsPassedOn(): void
    {
        chdir($this->root . '/app/src/Action');

        $this->handOff(['route:list']);

        $this->assertPassedOn(['route:list']);
    }

    public function testAComposerProjectWithoutCrestPhpRunsTheCallHere(): void
    {
        // composer.json and vendor/bin/crest, but no crest.php: not a crest
        // project. This crest runs the call and gives the crest init hint.
        unlink($this->root . '/app/crest.php');

        $this->assertStays($this->handOff(['route:list']));
    }

    public function testAConfigFileThatDoesNotExistRunsTheCallHere(): void
    {
        // This crest reports the file.
        chdir($this->root . '/other');

        $this->assertStays($this->handOff(['route:list', '--config=' . $this->root . '/missing.php']));
    }

    public function testAConfigFileWithAnotherNameReachesTheContainerByName(): void
    {
        // The container works in the root, the folder of the file. Without
        // its name, the crest there reads crest.php.
        file_put_contents(
            $this->root . '/app/crest.local.php',
            "<?php\n\nreturn ['runtime' => ['type' => 'docker']];\n"
        );
        chdir($this->root . '/other');

        $this->handOff(['route:list', '--config', $this->root . '/app/crest.local.php']);

        $this->assertSame(
            [[
                [
                    'docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'app',
                    'vendor/bin/crest', 'route:list', '--config=crest.local.php',
                ],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    public function testACrestPhpWithASyntaxErrorIsReported(): void
    {
        // One crest line, not a PHP trace.
        file_put_contents($this->root . '/app/crest.php', "<?php\n\nreturn [\n");

        $this->assertSame(1, $this->handOff(['route:list']));
        $this->assertSame([], $this->runner->calls);

        $stderr = $this->readStderr();

        $this->assertStringStartsWith("crest: Unclosed '['", $stderr);
        $this->assertStringNotContainsString('#0', $stderr);
    }

    public function testACrestPhpWithoutAComposerJsonIsReported(): void
    {
        unlink($this->root . '/app/composer.json');
        unlink($this->root . '/app/vendor/bin/crest');

        $this->assertSame(1, $this->handOff(['route:list']));
        $this->assertSame(
            'crest: ' . $this->root . "/app does not require phalcon/crest; run 'composer require --dev phalcon/crest'"
            . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testACrestPhpWithoutAReturnIsPassedOn(): void
    {
        // require gives 1, so there is no runtime key. The crest of the
        // project reads the file and reports it.
        file_put_contents($this->root . '/app/crest.php', "<?php\n");

        $this->handOff(['route:list']);

        $this->assertPassedOn(['route:list']);
    }

    public function testADirectoryOptionAfterTheDoubleDashIsAValue(): void
    {
        chdir($this->root . '/other');

        $this->assertStays($this->handOff(['route:list', '--', '--directory=' . $this->root . '/app']));
    }

    public function testADirectoryOptionBeforeAnotherOptionHasNoValue(): void
    {
        // As in the parser: a token that starts with '-' is not a value. The
        // project crest reports the missing value.
        $this->handOff(['route:list', '--directory', '--trace']);

        $this->assertPassedOn(['route:list', '--directory', '--trace']);
    }

    public function testADirectoryOptionWithAnEqualsSignIsLeftOutInTheContainer(): void
    {
        $this->runtime("['type' => 'docker']");

        $this->handOff(['route:list', '--directory=' . $this->root . '/app', '--trace']);

        $this->assertSame(
            [[
                [
                    'docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'app',
                    'vendor/bin/crest', 'route:list', '--trace',
                ],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    public function testADirectoryOptionWithoutAValueIsLeftOutInTheContainer(): void
    {
        $this->runtime("['type' => 'docker']");

        $this->handOff(['route:list', '--directory']);

        $this->assertSame(
            [[
                ['docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'app', 'vendor/bin/crest', 'route:list'],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    public function testADirectoryOptionWithoutAValueMeansTheWorkingDirectory(): void
    {
        // The project crest reports the missing value.
        $this->handOff(['route:list', '--directory']);

        $this->assertPassedOn(['route:list', '--directory']);
    }

    public function testADirectoryThatDoesNotExistRunsTheCallHere(): void
    {
        // This crest reports the directory, as it did before the hand-off.
        $this->assertStays($this->handOff(['route:list', '--directory=' . $this->root . '/missing']));
    }

    public function testADockerRuntimeRunsTheProjectCrestInItsService(): void
    {
        $this->runtime("['type' => 'docker', 'service' => 'web']");

        $status = $this->handOff(['route:list', '--trace'], 4);

        $this->assertSame(4, $status);
        $this->assertSame(
            [[
                [
                    'docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'web',
                    'vendor/bin/crest', 'route:list', '--trace',
                ],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    public function testAHostRuntimeRunsTheProjectCrestOnTheHost(): void
    {
        $this->runtime("['type' => 'host']");

        $this->handOff(['route:list']);

        $this->assertPassedOn(['route:list']);
    }

    public function testAMissingDockerIsReported(): void
    {
        // The real runner, with no docker on the PATH: one crest line.
        $this->runtime("['type' => 'docker']");
        $path = getenv('PATH');
        putenv('PATH=' . $this->root . '/other');

        try {
            $status = (new HandOff($this->root . '/self', new ShellRunner(), true))->run(
                ['crest', 'route:list'],
                new Output($this->stdout, $this->stderr, false)
            );
        } finally {
            putenv(false === $path ? 'PATH' : 'PATH=' . $path);
        }

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: 'docker' was not found; install it or add it to the PATH" . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testAnEmptyDirectoryOptionMeansTheWorkingDirectory(): void
    {
        $this->handOff(['route:list', '--directory=']);

        $this->assertPassedOn(['route:list', '--directory=']);
    }

    public function testANewerGlobalCrestIsReported(): void
    {
        putenv(HandOff::VARIABLE . '=' . (HandOff::PROTOCOL + 1));

        $status = $this->handOff(['route:list']);

        $this->assertSame(1, $status);
        $this->assertSame([], $this->runner->calls);
        $this->assertStringContainsString(
            'crest: the global crest is newer than the crest of this project (hand-off 2, this crest 1); '
            . "run 'composer update phalcon/crest' in the project",
            $this->readStderr()
        );
    }

    public function testAnOlderGlobalCrestIsReported(): void
    {
        putenv(HandOff::VARIABLE . '=0');

        $status = $this->handOff(['route:list']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'crest: the global crest is older than the crest of this project (hand-off 0, this crest 1); '
            . "run 'composer global update phalcon/crest'",
            $this->readStderr()
        );
    }

    public function testAnOptionThatOnlyStartsLikeConfigIsNotConfig(): void
    {
        // --configure is not --config. The project crest reports it.
        $this->handOff(['route:list', '--configure=x']);

        $this->assertPassedOn(['route:list', '--configure=x']);
    }

    public function testAnUnknownFlavorIsLeftToTheProjectCrest(): void
    {
        // A newer project crest can know a flavor that this crest does not.
        // This crest reads only the runtime key.
        file_put_contents($this->root . '/app/crest.php', "<?php\n\nreturn ['flavor' => 'micro'];\n");

        $this->handOff(['route:list']);

        $this->assertPassedOn(['route:list']);
    }

    public function testAPathValueAfterAnotherOptionIsLeftOutInTheContainer(): void
    {
        $this->runtime("['type' => 'docker']");

        $this->handOff(['route:list', '--trace', '--directory', $this->root . '/app']);

        $this->assertSame(
            [[
                [
                    'docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'app',
                    'vendor/bin/crest', 'route:list', '--trace',
                ],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    public function testAProjectCommandIsPassedOn(): void
    {
        $status = $this->handOff(['route:list', '--trace'], 3);

        $this->assertSame(3, $status);
        $this->assertPassedOn(['route:list', '--trace']);
    }

    public function testAProjectThatDoesNotRequireCrestIsReported(): void
    {
        // This crest does not have the autoloader and the Phalcon of the
        // project, so it does not run the call.
        file_put_contents($this->root . '/app/composer.json', '{"require": {"php": "^8.1"}}');
        unlink($this->root . '/app/vendor/bin/crest');

        $status = $this->handOff(['route:list']);

        $this->assertSame(1, $status);
        $this->assertSame([], $this->runner->calls);
        $this->assertSame(
            'crest: ' . $this->root . "/app does not require phalcon/crest; run 'composer require --dev phalcon/crest'"
            . PHP_EOL,
            $this->readStderr()
        );
    }

    /**
     * @dataProvider requirements
     */
    public function testAProjectThatRequiresCrestButHasNoBinaryIsReported(string $section): void
    {
        // No crest can run a project command before composer installs the
        // project.
        $this->requiring($section);
        unlink($this->root . '/app/vendor/bin/crest');

        $status = $this->handOff(['route:list']);

        $this->assertSame(1, $status);
        $this->assertSame([], $this->runner->calls);
        $this->assertSame(
            'crest: ' . $this->root . "/app has no vendor/bin/crest; run 'crest install' or 'composer install' first"
            . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testAProtocolThatIsNotANumberIsAnOlderProtocol(): void
    {
        // One crest line, no PHP warning.
        putenv(HandOff::VARIABLE . '=abc');

        $this->assertSame(1, $this->handOff(['route:list']));
        $this->assertStringContainsString('the global crest is older', $this->readStderr());
    }

    public function testARelativeConfigFileReachesTheContainerByName(): void
    {
        // Only the value is a path, not the whole token.
        file_put_contents(
            $this->root . '/app/crest.local.php',
            "<?php\n\nreturn ['runtime' => ['type' => 'docker']];\n"
        );

        $this->handOff(['route:list', '--config=crest.local.php']);

        $this->assertSame(
            [[
                [
                    'docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'app',
                    'vendor/bin/crest', 'route:list', '--config=crest.local.php',
                ],
                '.',
            ]],
            $this->runner->calls
        );
    }

    public function testARelativeDirectoryOptionStartsInTheWorkingDirectory(): void
    {
        // The project crest reads the same path from the same working
        // directory.
        chdir($this->root . '/other');

        $this->handOff(['route:list', '--directory=../app']);

        $this->assertPassedOn(['route:list', '--directory=../app']);
    }

    public function testArgumentsReachTheProjectCrestUnchanged(): void
    {
        $tokens = ['make:action', 'GET', '/company/{id}', '--force', '--', '--help'];

        $this->handOff($tokens);

        $this->assertPassedOn($tokens);
    }

    public function testDockerGetsNoTerminalWhenStdinIsNotOne(): void
    {
        // CI or a pipe: docker compose exec must not ask for a TTY.
        $this->runtime("['type' => 'docker']");

        $this->handOff(['route:list'], 0, null, false);

        $this->assertSame(
            [[
                ['docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', '-T', 'app', 'vendor/bin/crest', 'route:list'],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    /**
     * @dataProvider hostCommands
     */
    public function testHostCommandsRunHere(string $command): void
    {
        $this->assertStays($this->handOff([$command, 'my-app']));
    }

    public function testHostPathsDoNotReachTheContainer(): void
    {
        // --directory and --config are host paths. The root is already found.
        // The container gets only the name of the config file.
        $this->runtime("['type' => 'docker']");
        chdir($this->root . '/other');

        $this->handOff([
            'route:list',
            '--directory',
            $this->root . '/app',
            '--config=' . $this->root . '/app/crest.php',
            '--trace',
        ]);

        $this->assertSame(
            [[
                [
                    'docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'app',
                    'vendor/bin/crest', 'route:list', '--config=crest.php', '--trace',
                ],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    public function testOutsideAProjectTheCallRunsHere(): void
    {
        chdir($this->root . '/other');

        $this->assertStays($this->handOff(['route:list']));
    }

    /**
     * @dataProvider serveCommands
     */
    public function testServeRunsOnTheHostWithADockerRuntime(string $command): void
    {
        // A8: PHP's built-in server must listen on the host, not in the
        // container.
        $this->runtime("['type' => 'docker']");

        $this->handOff([$command, '--port', '8099']);

        $this->assertPassedOn([$command, '--port', '8099']);
    }

    public function testTheConfigOptionFindsTheProject(): void
    {
        chdir($this->root . '/other');

        $tokens = ['route:list', '--config', $this->root . '/app/crest.php'];

        $this->handOff($tokens);

        $this->assertPassedOn($tokens);
    }

    public function testTheConfigOptionWithAnEqualsSignFindsTheProject(): void
    {
        chdir($this->root . '/other');

        $tokens = ['route:list', '--config=' . $this->root . '/app/crest.php'];

        $this->handOff($tokens);

        $this->assertPassedOn($tokens);
    }

    public function testTheCrestOfTheProjectFindsItselfByTheComposerPath(): void
    {
        // The composer proxy names vendor/bin/../autoload.php.
        $this->assertStays($this->handOff(['route:list'], 0, $this->root . '/app/vendor/bin/..'));
    }

    public function testTheCrestOfTheProjectFindsItselfWithARelativeConfigFile(): void
    {
        // The root is then '.', not the full path.
        $this->assertStays($this->handOff(['route:list', '--config', 'crest.php'], 0, $this->root . '/app/vendor'));
    }

    public function testTheCrestOfTheProjectRunsTheCallItself(): void
    {
        // vendor/bin/crest of the project loads the autoloader in its vendor/.
        // A hand-off here would never stop.
        $this->assertStays($this->handOff(['route:list'], 0, $this->root . '/app/vendor'));
    }

    public function testTheDirectoryOptionFindsTheProject(): void
    {
        chdir($this->root . '/other');

        $this->handOff(['route:list', '--directory', $this->root . '/app']);

        $this->assertPassedOn(['route:list', '--directory', $this->root . '/app']);
    }

    public function testTheDirectoryOptionWithAnEqualsSignFindsTheProject(): void
    {
        chdir($this->root . '/other');

        $this->handOff(['route:list', '--directory=' . $this->root . '/app']);

        $this->assertPassedOn(['route:list', '--directory=' . $this->root . '/app']);
    }

    public function testTheLastDirectoryOptionWins(): void
    {
        // As in the parser, which keeps the last value.
        chdir($this->root . '/other');

        $tokens = [
            'route:list',
            '--directory=' . $this->root . '/other',
            '--directory',
            $this->root . '/app',
        ];

        $this->handOff($tokens);

        $this->assertPassedOn($tokens);
    }

    /**
     * @dataProvider listings
     *
     * @param list<string> $tokens
     */
    public function testTheListingIsPassedOn(array $tokens): void
    {
        // The listing of the project also shows the commands of its packages.
        $this->handOff($tokens);

        $this->assertPassedOn($tokens);
    }

    public function testTheListingWithAConfigFileReachesTheContainerByName(): void
    {
        $this->runtime("['type' => 'docker']");
        chdir($this->root . '/other');

        $this->handOff(['--config=' . $this->root . '/app/crest.php']);

        $this->assertSame(
            [[
                ['docker', 'compose', 'exec', '-e', 'CREST_HANDOFF=1', 'app', 'vendor/bin/crest', '--config=crest.php'],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    public function testTheProjectCrestGetsTheProtocolOnTheHost(): void
    {
        $this->handOff(['route:list']);

        $this->assertSame([[HandOff::VARIABLE => '1']], $this->runner->environments);
    }

    public function testTheProtocolCoversTheRoutingLists(): void
    {
        // A snapshot. When it fails, change HandOff::PROTOCOL too: a global
        // crest and the crest of a project can be different versions.
        $this->assertSame(
            [1, ['down', 'install', 'up', 'init', 'new'], ['serve', 'server'], ['config', 'directory']],
            [
                HandOff::PROTOCOL,
                Commands::HOST,
                Commands::ON_HOST,
                array_map(static fn (Option $option): string => $option->name, Kernel::paths()->getOptions()),
            ],
            'The hand-off lists changed. Change HandOff::PROTOCOL, then this snapshot.'
        );
    }

    public function testTheSameProtocolRunsTheCall(): void
    {
        putenv(HandOff::VARIABLE . '=' . HandOff::PROTOCOL);

        $this->assertStays($this->handOff(['route:list'], 0, $this->root . '/app/vendor'));
    }

    /**
     * @dataProvider versionFlags
     */
    public function testTheVersionIsTheVersionOfThisCrest(string $flag): void
    {
        $this->assertStays($this->handOff([$flag]));
    }

    public function testValuesAfterTheDoubleDashReachTheContainer(): void
    {
        $this->runtime("['type' => 'docker']");

        $this->handOff(['make:action', 'GET', '/x', '--', '--directory', 'y']);

        $this->assertSame(
            [[
                [
                    'docker',
                    'compose',
                    'exec',
                    '-e',
                    'CREST_HANDOFF=1',
                    'app',
                    'vendor/bin/crest',
                    'make:action',
                    'GET',
                    '/x',
                    '--',
                    '--directory',
                    'y',
                ],
                $this->root . '/app',
            ]],
            $this->runner->calls
        );
    }

    /**
     * @param list<string> $tokens
     */
    private function assertPassedOn(array $tokens): void
    {
        $this->assertSame(
            [[[PHP_BINARY, $this->root . '/app/vendor/bin/crest', ...$tokens], null]],
            $this->runner->calls
        );
    }

    private function assertStays(?int $status): void
    {
        $this->assertNull($status);
        $this->assertSame([], $this->runner->calls);
    }

    /**
     * @param list<string> $tokens   The arguments after `crest`.
     * @param string|null  $vendor   The vendor folder of the running crest.
     * @param bool         $terminal Whether stdin is a terminal.
     */
    private function handOff(array $tokens, int $status = 0, ?string $vendor = null, bool $terminal = true): ?int
    {
        $this->runner = new FakeRunner($status);

        return (new HandOff($vendor ?? $this->root . '/self', $this->runner, $terminal))->run(
            ['crest', ...$tokens],
            new Output($this->stdout, $this->stderr, false)
        );
    }

    private function requiring(string $section): void
    {
        file_put_contents(
            $this->root . '/app/composer.json',
            (string) json_encode([$section => ['phalcon/crest' => 'dev-master']])
        );
    }

    private function runtime(string $runtime): void
    {
        file_put_contents($this->root . '/app/crest.php', "<?php\n\nreturn ['runtime' => " . $runtime . "];\n");
    }
}
