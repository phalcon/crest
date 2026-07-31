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

namespace Crest\Tests\Unit\Command\Event;

use Crest\Command\Event\ListCommand;
use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\Project\EmptyFront;
use Crest\Tests\Support\Project\EventsFront;
use Crest\Tests\Support\Project\ManagerOnlyFront;
use Crest\Tests\Support\Project\WrongContainerFront;
use Crest\Tests\Support\Project\WrongManagerFront;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function preg_replace;
use function str_replace;

use const PHP_EOL;

final class ListCommandTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('event-list', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testAContainerThatRegistersNoManagerSaysSo(): void
    {
        // The container would happily autowire a fresh Manager, and reporting
        // "no listeners attached" off that would read as a fact about the
        // application rather than about its bootstrap.
        $this->declareFront(EmptyFront::class);

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'the bootstrap registers no Phalcon\Events\Manager; without one there are '
            . 'no listeners to list',
            $this->readStderr()
        );
    }

    public function testAManagerWithNoListenersSaysSo(): void
    {
        $this->declareFront(ManagerOnlyFront::class);

        $status = $this->runCommand();

        $this->assertSame(0, $status);
        $this->assertSame('no listeners attached' . PHP_EOL, $this->readStdout());
    }

    public function testANonPhalconContainerIsReported(): void
    {
        $this->declareFront(WrongContainerFront::class);

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('stdClass is not a Phalcon container', $this->readStderr());
    }

    public function testDefinitionNamesItselfEventList(): void
    {
        $this->assertSame('event:list', (new ListCommand())->define()->getName());
    }

    public function testListenersAreListedSortedByEvent(): void
    {
        $this->declareFront(EventsFront::class);

        $status = $this->runCommand();

        $expected = 'EVENT LISTENER' . PHP_EOL
            . 'alpha Phalcon\Support\HelperFactory' . PHP_EOL
            . 'boot Closure' . PHP_EOL
            . 'zebra:fired Phalcon\Support\HelperFactory' . PHP_EOL;

        $this->assertSame(0, $status);
        $this->assertSame($expected, $this->normalised());
    }

    public function testSomethingElseRegisteredAsTheManagerIsReported(): void
    {
        // Declared under the right name, so the registration check passes and
        // only the type check catches it.
        $this->declareFront(WrongManagerFront::class);

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'Phalcon\Events\Manager resolved to something else',
            $this->readStderr()
        );
    }

    private function declareFront(string $class): void
    {
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['bootstrap' => '" . str_replace('\\', '\\\\', $class) . "'];\n"
        );
    }

    private function normalised(): string
    {
        return (string) preg_replace('/ {2,}/', ' ', $this->readStdout());
    }

    private function runCommand(): int
    {
        $registry = (new Registry())->add('event:list', ListCommand::class);
        $kernel   = new Kernel(
            Commands::NAME,
            $registry,
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', 'event:list', '--directory', $this->root]);
    }
}
