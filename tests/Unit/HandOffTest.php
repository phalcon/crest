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

use Crest\Console\Output;
use Crest\HandOff;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\Process\FakeRunner;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chdir;
use function file_put_contents;
use function getcwd;
use function json_encode;
use function mkdir;
use function unlink;

use const PHP_BINARY;
use const PHP_EOL;

/**
 * The scratch root has three directories: app/ is a project that requires
 * crest and has vendor/bin/crest, other/ is not a project, and self/ is the
 * running crest.
 *
 * A walk up from other/ finds the composer.json of this repository. It has no
 * vendor/bin/crest, and it does not require phalcon/crest, so the call stays.
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

        file_put_contents($this->root . '/app/vendor/bin/crest', "<?php\n");

        $this->runner = new FakeRunner();

        // Most calls start in the project, as the calls of a user who works
        // in it.
        $this->previousCwd = (string) getcwd();
        chdir($this->root . '/app');
    }

    protected function tearDown(): void
    {
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
    public static function versionFlags(): iterable
    {
        yield 'long' => ['--version'];
        yield 'short' => ['-V'];
    }

    public function testACallFromASubdirectoryIsPassedOn(): void
    {
        chdir($this->root . '/app/src/Action');

        $this->handOff(['route:list']);

        $this->assertPassedOn(['route:list']);
    }

    public function testADirectoryOptionAfterTheDoubleDashIsAValue(): void
    {
        chdir($this->root . '/other');

        $this->assertStays($this->handOff(['route:list', '--', '--directory=' . $this->root . '/app']));
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

    public function testAnEmptyDirectoryOptionMeansTheWorkingDirectory(): void
    {
        $this->handOff(['route:list', '--directory=']);

        $this->assertPassedOn(['route:list', '--directory=']);
    }

    public function testAProjectCommandIsPassedOn(): void
    {
        $status = $this->handOff(['route:list', '--trace'], 3);

        $this->assertSame(3, $status);
        $this->assertPassedOn(['route:list', '--trace']);
    }

    public function testAProjectThatDoesNotRequireCrestRunsTheCallHere(): void
    {
        // Not a crest project: this crest runs the call, as before.
        file_put_contents($this->root . '/app/composer.json', '{"require": {"php": "^8.1"}}');
        unlink($this->root . '/app/vendor/bin/crest');

        $this->assertStays($this->handOff(['route:list']));
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

    /**
     * @dataProvider hostCommands
     */
    public function testHostCommandsRunHere(string $command): void
    {
        $this->assertStays($this->handOff([$command, 'my-app']));
    }

    public function testOutsideAProjectTheCallRunsHere(): void
    {
        chdir($this->root . '/other');

        $this->assertStays($this->handOff(['route:list']));
    }

    public function testTheCrestOfTheProjectRunsTheCallItself(): void
    {
        // vendor/bin/crest, run directly. A hand-off here would never stop.
        mkdir($this->root . '/app/vendor/phalcon/crest', 0o775, true);

        $this->assertStays($this->handOff(['route:list'], 0, $this->root . '/app/vendor/phalcon/crest'));
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

    /**
     * @dataProvider versionFlags
     */
    public function testTheVersionIsTheVersionOfThisCrest(string $flag): void
    {
        $this->assertStays($this->handOff([$flag]));
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
     * @param list<string> $tokens The arguments after `crest`.
     * @param string|null  $self   The root of the running crest.
     */
    private function handOff(array $tokens, int $status = 0, ?string $self = null): ?int
    {
        $this->runner = new FakeRunner($status);

        return (new HandOff($this->runner, $self ?? $this->root . '/self'))->run(
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
}
