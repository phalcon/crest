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

namespace Crest\Tests\Unit\Command\Container;

use Crest\Command\Container\ListCommand;
use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\Project\EmptyFront;
use Crest\Tests\Support\Project\FailingFront;
use Crest\Tests\Support\Project\NoBootFront;
use Crest\Tests\Support\Project\NonContainerFront;
use Crest\Tests\Support\Project\ServicesFront;
use Crest\Tests\Support\Project\WrongContainerFront;
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
        $this->makeScratchDirectory('container-list', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testABootReturningANonObjectIsReported(): void
    {
        $this->declareFront(NonContainerFront::class);

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            NonContainerFront::class . '::boot() did not return a container',
            $this->readStderr()
        );
    }

    public function testABootReturningSomethingOtherThanAPhalconContainerIsReported(): void
    {
        $this->declareFront(WrongContainerFront::class);

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('stdClass is not a Phalcon container', $this->readStderr());
    }

    public function testABootThatThrowsIsReportedAsABootFailure(): void
    {
        $this->declareFront(FailingFront::class);

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'the project failed to boot: no database',
            $this->readStderr()
        );
    }

    public function testAFrontWithNoBootIsReported(): void
    {
        $this->declareFront(NoBootFront::class);

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            NoBootFront::class . ' has no boot(); without one it cannot be started '
            . 'without also serving a request',
            $this->readStderr()
        );
    }

    public function testAnEmptyContainerSaysSo(): void
    {
        $this->declareFront(EmptyFront::class);

        $status = $this->runCommand();

        $this->assertSame(0, $status);
        $this->assertSame('no services registered' . PHP_EOL, $this->readStdout());
    }

    public function testAnUnknownFrontControllerIsReported(): void
    {
        $this->declareFront('App\Front\NoSuchFront');

        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('was not found', $this->readStderr());
    }

    public function testDefinitionNamesItselfContainerList(): void
    {
        $this->assertSame('container:list', (new ListCommand())->define()->getName());
    }

    public function testServicesAreListedSortedWithClassAndResolvedState(): void
    {
        $this->declareFront(ServicesFront::class);

        $status = $this->runCommand();

        $expected = 'SERVICE CLASS RESOLVED' . PHP_EOL
            . 'alpha Phalcon\Support\HelperFactory yes' . PHP_EOL
            . 'zebra Phalcon\Support\HelperFactory no' . PHP_EOL;

        $this->assertSame(0, $status);
        $this->assertSame($expected, $this->normalized());
    }

    public function testWithoutABootstrapItSaysWhatToAdd(): void
    {
        $status = $this->runCommand();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "name the front controller in crest.php, e.g. 'bootstrap' => App\\Front\\ApiFront::class",
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

    private function normalized(): string
    {
        return (string) preg_replace('/ {2,}/', ' ', $this->readStdout());
    }

    private function runCommand(): int
    {
        $registry = (new Registry())->add('container:list', ListCommand::class);
        $kernel   = new Kernel(
            Commands::NAME,
            $registry,
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', 'container:list', '--directory', $this->root]);
    }
}
