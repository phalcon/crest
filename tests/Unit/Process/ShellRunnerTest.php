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

namespace Crest\Tests\Unit\Process;

use Crest\Console\Exceptions\Exception;
use Crest\Process\ShellRunner;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function getenv;
use function putenv;

use const PHP_BINARY;

final class ShellRunnerTest extends TestCase
{
    use ScratchDirectory;

    private false | string $savedPath = false;

    private false | string $savedPathext = false;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('shell-runner', 'bin', 'work');
        $this->savedPath    = getenv('PATH');
        $this->savedPathext = getenv('PATHEXT');
    }

    protected function tearDown(): void
    {
        putenv(false === $this->savedPath ? 'PATH' : 'PATH=' . $this->savedPath);
        putenv(false === $this->savedPathext ? 'PATHEXT' : 'PATHEXT=' . $this->savedPathext);

        $this->removeScratchDirectory();
    }

    public function testABareNameIsFoundOnThePath(): void
    {
        $this->executable('bin/hello', "#!/bin/sh\nexit 4\n");
        putenv('PATH=' . $this->root . '/bin');

        $this->assertSame(4, (new ShellRunner())->run(['hello']));
    }

    public function testAMissingDirectoryIsReportedNotIgnored(): void
    {
        // proc_open() ignores a working directory that does not exist, and
        // the child runs in crest's own directory. For `down`, that is the
        // wrong project.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($this->root . '/missing is not a directory');

        (new ShellRunner())->run([PHP_BINARY, '-r', 'exit(0);'], $this->root . '/missing');
    }

    public function testAMissingProgramIsReportedByName(): void
    {
        putenv('PATH=' . $this->root . '/bin');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'docker' was not found; install it or add it to the PATH");

        (new ShellRunner())->run(['docker', 'compose', 'up', '-d']);
    }

    public function testANameWithItsEndingIsFoundWhenPathextIsSet(): void
    {
        // Windows: `docker.exe` is found as it is, not as `docker.exe.exe`.
        $this->executable('bin/hello.cmd', "#!/bin/sh\nexit 0\n");
        putenv('PATH=' . $this->root . '/bin');
        putenv('PATHEXT=.cmd');

        $this->expectFoundProgram();

        (new ShellRunner())->run(['hello.cmd'], $this->root . '/missing');
    }

    public function testAnEmptyPathEntryIsSkipped(): void
    {
        // An empty entry does not name the filesystem root, and it does not
        // stop the search.
        $this->executable('bin/hello', "#!/bin/sh\nexit 4\n");
        putenv('PATH=:' . $this->root . '/bin');

        $this->assertSame(4, (new ShellRunner())->run(['hello']));
    }

    public function testANonExecutableFileIsNotRun(): void
    {
        file_put_contents($this->root . '/bin/plain', "#!/bin/sh\nexit 0\n");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'" . $this->root . "/bin/plain' was not found");

        (new ShellRunner())->run([$this->root . '/bin/plain']);
    }

    public function testANonExecutableFileOnThePathIsNotFound(): void
    {
        file_put_contents($this->root . '/bin/plain', "#!/bin/sh\nexit 0\n");
        putenv('PATH=' . $this->root . '/bin');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'plain' was not found");

        (new ShellRunner())->run(['plain']);
    }

    public function testAnUnsetPathFindsNothing(): void
    {
        putenv('PATH');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'sh' was not found");

        (new ShellRunner())->run(['sh']);
    }

    public function testPathextGivesTheEndingsToTry(): void
    {
        // Windows finds `docker` as `docker.exe`. The match is the second
        // entry, so every entry is tried.
        $this->executable('bin/hello.cmd', "#!/bin/sh\nexit 0\n");
        putenv('PATH=' . $this->root . '/bin');
        putenv('PATHEXT=.BAT;.cmd');

        $this->expectFoundProgram();

        (new ShellRunner())->run(['hello'], $this->root . '/missing');
    }

    public function testTheExitStatusIsReturned(): void
    {
        $this->assertSame(3, (new ShellRunner())->run([PHP_BINARY, '-r', 'exit(3);']));
    }

    public function testTheProgramRunsInTheGivenDirectory(): void
    {
        (new ShellRunner())->run(
            [PHP_BINARY, '-r', 'file_put_contents("marker", "here");'],
            $this->root . '/work'
        );

        $this->assertFileExists($this->root . '/work/marker');
    }

    private function executable(string $path, string $contents): void
    {
        file_put_contents($this->root . '/' . $path, $contents);
        chmod($this->root . '/' . $path, 0o755);
    }

    /**
     * Linux cannot start `hello` as `hello.cmd`. The directory check comes
     * after the program check, so its error shows that the program was found.
     */
    private function expectFoundProgram(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($this->root . '/missing is not a directory');
    }
}
