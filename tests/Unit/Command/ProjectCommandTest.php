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

use Crest\Command\Config\ShowCommand;
use Crest\Command\Container\ListCommand as ContainerListCommand;
use Crest\Command\Event\ListCommand as EventListCommand;
use Crest\Command\Make\ActionCommand;
use Crest\Command\Make\MiddlewareCommand;
use Crest\Command\Make\ProviderCommand;
use Crest\Command\Make\ResponderCommand;
use Crest\Command\Route\ListCommand as RouteListCommand;
use Crest\Command\Stub\PublishCommand;
use Crest\Console\Command\Command;
use Crest\Generator\Stub;
use Crest\Project\Flavor;
use Crest\Tests\Support\Command\FlavoredCommand;
use Crest\Tests\Support\GeneratesInAScratchProject;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function sort;

use const PHP_EOL;

/**
 * The flavor rule of the project commands: ProjectCommand::supports(), and
 * the check in ProjectCommand::config().
 */
final class ProjectCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('project-command', 'src');
        $this->writeCrestPhp("['flavor' => 'mvc']");
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    /**
     * Each command that has no MVC version yet, with the arguments that it
     * needs to pass the binding.
     *
     * @return iterable<string, array{string, class-string<Command>, list<string>}>
     */
    public static function adrOnly(): iterable
    {
        yield 'container:list' => ['container:list', ContainerListCommand::class, []];
        yield 'event:list' => ['event:list', EventListCommand::class, []];
        yield 'make:action' => ['make:action', ActionCommand::class, ['GET', '/albums']];
        yield 'make:middleware' => ['make:middleware', MiddlewareCommand::class, ['Auth']];
        yield 'make:provider' => ['make:provider', ProviderCommand::class, ['Cache']];
        yield 'make:responder' => ['make:responder', ResponderCommand::class, ['Album']];
        yield 'route:list' => ['route:list', RouteListCommand::class, []];
    }

    public function testACommandRunsInEachFlavorThatItDeclares(): void
    {
        $status = $this->runProjectCommand('flavored', FlavoredCommand::class, []);

        $this->assertSame(0, $status);
        $this->assertSame('ran in mvc' . PHP_EOL, $this->readStdout());
    }

    public function testAnAdrOnlyCommandStopsInACliProject(): void
    {
        $this->writeCrestPhp("['flavor' => 'cli']");

        $status = $this->runProjectCommand('make:responder', ResponderCommand::class, ['Album']);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: make:responder does not support the 'cli' flavor of this project; it supports: adr" . PHP_EOL,
            $this->readStderr()
        );
    }

    /**
     * @dataProvider adrOnly
     *
     * @param class-string<Command> $class
     * @param list<string>          $arguments
     */
    public function testAnAdrOnlyCommandStopsInAnMvcProject(string $name, string $class, array $arguments): void
    {
        $before = $this->files();

        $status = $this->runProjectCommand($name, $class, $arguments);

        $this->assertSame(1, $status);
        $this->assertSame('', $this->readStdout());
        $this->assertSame(
            'crest: ' . $name . " does not support the 'mvc' flavor of this project; it supports: adr" . PHP_EOL,
            $this->readStderr()
        );
        $this->assertSame($before, $this->files());
    }

    public function testConfigShowRunsInAnMvcProject(): void
    {
        $status = $this->runProjectCommand('config:show', ShowCommand::class, []);

        $stdout = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/^flavor +mvc +declared$/m', $stdout);
        $this->assertMatchesRegularExpression('#^command +\S*/src/Command +default$#m', $stdout);
        $this->assertMatchesRegularExpression('#^controller +\S*/src/Controllers +default$#m', $stdout);
        $this->assertSame('', $this->readStderr());
    }

    public function testStubPublishRunsInAnMvcProject(): void
    {
        // MVC has stubs of its own, and the shared folder gives it
        // command.stub.
        $status = $this->runProjectCommand('stub:publish', PublishCommand::class, []);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, 'mvc', 'controller'));
        $this->assertFileExists(Stub::overridePath($this->root, Stub::SHARED, 'command'));
        $this->assertSame('', $this->readStderr());
    }

    public function testSupportsIsTrueOnlyForTheDeclaredFlavors(): void
    {
        $command = new FlavoredCommand();

        $this->assertTrue($command->supports(Flavor::ADR));
        $this->assertTrue($command->supports(Flavor::MVC));
        $this->assertFalse($command->supports(Flavor::CLI));
    }

    public function testTheErrorListsEachSupportedFlavor(): void
    {
        $this->writeCrestPhp("['flavor' => 'cli']");

        $status = $this->runProjectCommand('flavored', FlavoredCommand::class, []);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: flavored does not support the 'cli' flavor of this project; it supports: adr, mvc" . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testTheHelpOfAnAdrOnlyCommandStillPrints(): void
    {
        // The kernel prints the help before handle(), so the check does not
        // run.
        $status = $this->runProjectCommand('make:responder', ResponderCommand::class, ['--help']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Usage: crest make:responder', $this->readStdout());
        $this->assertSame('', $this->readStderr());
    }

    /**
     * Each file under the scratch root, sorted.
     *
     * @return list<string>
     */
    private function files(): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }
}
