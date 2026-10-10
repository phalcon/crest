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
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function chmod;
use function fclose;
use function file_put_contents;
use function implode;
use function mkdir;
use function proc_close;
use function proc_open;
use function stream_get_contents;

use const PHP_EOL;

/**
 * Runs the launcher that `crest new` writes (./crest), as a user does. A fake
 * docker, first on the PATH, prints its working folder and then each argument
 * on its own line, and exits with status 3. stdin is a pipe, not a terminal,
 * so the launcher adds -T.
 */
final class LauncherTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('launcher', 'bin');

        file_put_contents(
            $this->root . '/bin/docker',
            "#!/bin/sh\n\npwd\nprintf '%s\\n' \"\$@\"\n\nexit 3\n"
        );
        chmod($this->root . '/bin/docker', 0o755);
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testACallFromASubfolderRunsInTheProjectRoot(): void
    {
        $project = $this->create();
        $this->install($project);

        [$status, $stdout] = $this->launch($project . '/src/Action', '../../crest', ['route:list']);

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'app', 'vendor/bin/crest', 'route:list'),
            $stdout
        );
    }

    public function testAFolderWithASpaceAboveTheProjectIsNoProblem(): void
    {
        $parent = $this->root . '/my projects';

        $this->runThroughKernel('new', NewCommand::class, ['my-app', '--directory', $parent]);

        $project = $parent . '/my-app';
        $this->install($project);

        [$status, $stdout] = $this->launch($this->root, $project . '/crest', ['route:list']);

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'app', 'vendor/bin/crest', 'route:list'),
            $stdout
        );
    }

    public function testAnArgumentWithASpaceReachesTheServiceUnchanged(): void
    {
        $project = $this->create();
        $this->install($project);

        [$status, $stdout] = $this->launch($project, './crest', ['make:action', 'GET', '/a b']);

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'app', 'vendor/bin/crest', 'make:action', 'GET', '/a b'),
            $stdout
        );
    }

    public function testAnExportedCdpathDoesNotMoveTheLauncher(): void
    {
        // With CDPATH, a relative `cd my-app` can go to another folder with
        // the same name, and cd then prints that folder.
        $project = $this->create();
        $this->install($project);
        mkdir($this->root . '/elsewhere/my-app', 0o775, true);

        [$status, $stdout] = $this->launch(
            $this->root,
            'my-app/crest',
            ['route:list'],
            ['CDPATH' => $this->root . '/elsewhere']
        );

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'app', 'vendor/bin/crest', 'route:list'),
            $stdout
        );
    }

    public function testAProjectCommandBeforeTheInstallIsRefused(): void
    {
        // O1: without vendor/bin/crest, docker does not run.
        $project = $this->create();

        [$status, $stdout, $stderr] = $this->launch($project, './crest', ['route:list']);

        $this->assertSame(1, $status);
        $this->assertSame('', $stdout);
        $this->assertSame("crest: run './crest install' first" . PHP_EOL, $stderr);
    }

    public function testAProjectCommandRunsInTheService(): void
    {
        $project = $this->create();
        $this->install($project);

        [$status, $stdout] = $this->launch($project, './crest', ['route:list', '--trace']);

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'app', 'vendor/bin/crest', 'route:list', '--trace'),
            $stdout
        );
    }

    public function testDownStopsTheContainersOnTheHost(): void
    {
        $project = $this->create();

        [$status, $stdout] = $this->launch($project, './crest', ['down', '--volumes']);

        $this->assertSame(3, $status);
        $this->assertSame($this->docker($project, 'compose', 'down', '--volumes'), $stdout);
    }

    public function testInstallRunsComposerInTheService(): void
    {
        // No vendor/bin/crest yet: install makes it.
        $project = $this->create();

        [$status, $stdout] = $this->launch($project, './crest', ['install', '--no-dev']);

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'app', 'composer', 'install', '--no-dev'),
            $stdout
        );
    }

    public function testNoArgumentGivesTheListingOfTheService(): void
    {
        $project = $this->create();
        $this->install($project);

        [$status, $stdout] = $this->launch($project, './crest', []);

        $this->assertSame(3, $status);
        $this->assertSame($this->docker($project, 'compose', 'exec', '-T', 'app', 'vendor/bin/crest'), $stdout);
    }

    public function testShRunsTheLauncherWithoutItsMode(): void
    {
        // A copy that lost the executable bit.
        $project = $this->create();
        $this->install($project);
        chmod($project . '/crest', 0o644);

        [$status, $stdout] = $this->launch($project, 'sh', ['crest', 'route:list']);

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'app', 'vendor/bin/crest', 'route:list'),
            $stdout
        );
    }

    public function testTheServiceComesFromNew(): void
    {
        $project = $this->create(['--service', 'web']);
        $this->install($project);

        [$status, $stdout] = $this->launch($project, './crest', ['route:list']);

        $this->assertSame(3, $status);
        $this->assertSame(
            $this->docker($project, 'compose', 'exec', '-T', 'web', 'vendor/bin/crest', 'route:list'),
            $stdout
        );
    }

    public function testUpStartsTheContainersOnTheHost(): void
    {
        $project = $this->create();

        [$status, $stdout] = $this->launch($project, './crest', ['up', '--build']);

        $this->assertSame(3, $status);
        $this->assertSame($this->docker($project, 'compose', 'up', '-d', '--build'), $stdout);
    }

    /**
     * Runs `crest new my-app` in the scratch root, without questions.
     *
     * @param list<string> $options
     *
     * @return string The project folder.
     */
    private function create(array $options = []): string
    {
        $this->runProjectCommand('new', NewCommand::class, ['my-app', ...$options]);

        return $this->root . '/my-app';
    }

    /**
     * The output of the fake docker: its working folder, then each argument.
     */
    private function docker(string $folder, string ...$arguments): string
    {
        return $folder . PHP_EOL . implode(PHP_EOL, $arguments) . PHP_EOL;
    }

    /**
     * After `./crest install`, the project has vendor/bin/crest.
     */
    private function install(string $project): void
    {
        mkdir($project . '/vendor/bin', 0o775, true);
        file_put_contents($project . '/vendor/bin/crest', '');
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $environment More variables, after PATH.
     *
     * @return array{int, string, string} The exit status, stdout and stderr.
     */
    private function launch(string $folder, string $command, array $arguments, array $environment = []): array
    {
        $process = proc_open(
            [$command, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $folder,
            ['PATH' => $this->root . '/bin:/usr/bin:/bin', ...$environment]
        );

        if (false === $process) {
            throw new RuntimeException('could not start ' . $command);
        }

        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
