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

use Crest\Command\UpCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Tests\Support\Process\FakeRunner;
use Crest\Tests\Support\RunsACommandDirectly;
use Crest\Tests\Support\RunsThroughTheKernel;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chdir;
use function getcwd;
use function getenv;
use function putenv;
use function unlink;

use const PHP_EOL;

final class UpCommandTest extends TestCase
{
    use RunsACommandDirectly;
    use RunsThroughTheKernel;
    use ScratchDirectory;

    private string $previousCwd = '';

    private false | string $savedPath = false;

    protected function setUp(): void
    {
        $this->captureStreams();
        $this->makeScratchDirectory('up', 'bin', 'src');
        $this->writeCrestPhp();
        $this->savedPath   = getenv('PATH');
        $this->previousCwd = (string) getcwd();
    }

    protected function tearDown(): void
    {
        putenv(false === $this->savedPath ? 'PATH' : 'PATH=' . $this->savedPath);
        chdir($this->previousCwd);

        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testAMissingDockerIsReportedAsACrestError(): void
    {
        // The real runner, through the real kernel, with no docker on the
        // PATH. The user sees one crest line, not a PHP warning.
        putenv('PATH=' . $this->root . '/bin');

        $status = $this->runThroughKernel('up', UpCommand::class, ['--directory', $this->root]);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: 'docker' was not found; install it or add it to the PATH" . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testAnEmptyDirectoryOptionMeansTheWorkingDirectory(): void
    {
        // `--directory="$DIR"` with an unset variable. As for `new`, empty
        // reads as absent, not as a directory named ''.
        chdir($this->root);

        $runner = new FakeRunner();

        $this->handleDirectly(new UpCommand($runner), ['--directory=']);

        $this->assertSame([[['docker', 'compose', 'up', '-d'], $this->root]], $runner->calls);
    }

    public function testBuildRebuildsTheImagesFirst(): void
    {
        $runner = new FakeRunner();

        $this->handleDirectly(new UpCommand($runner), ['--build', '--directory', $this->root]);

        $this->assertSame([[['docker', 'compose', 'up', '-d', '--build'], $this->root]], $runner->calls);
    }

    public function testComposeRunsInTheRootAboveTheDirectoryOption(): void
    {
        // The root rule of all commands: the nearest crest.php above.
        $runner = new FakeRunner();

        $this->handleDirectly(new UpCommand($runner), ['--directory', $this->root . '/src']);

        $this->assertSame([[['docker', 'compose', 'up', '-d'], $this->root]], $runner->calls);
    }

    public function testDefinitionNamesItselfUp(): void
    {
        $this->assertSame('up', (new UpCommand())->define()->getName());
    }

    public function testTheContainersStartDetached(): void
    {
        $runner = new FakeRunner();

        $status = $this->handleDirectly(new UpCommand($runner), ['--directory', $this->root]);

        $this->assertSame(0, $status);
        $this->assertSame([[['docker', 'compose', 'up', '-d'], $this->root]], $runner->calls);
    }

    public function testTheExitStatusOfComposeIsReturned(): void
    {
        $this->assertSame(
            5,
            $this->handleDirectly(new UpCommand(new FakeRunner(5)), ['--directory', $this->root])
        );
    }

    public function testWithoutCrestPhpUpStopsWithTheInitHint(): void
    {
        unlink($this->root . '/crest.php');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no crest.php found; run 'crest init'");

        $this->handleDirectly(new UpCommand(new FakeRunner()), ['--directory', $this->root]);
    }
}
