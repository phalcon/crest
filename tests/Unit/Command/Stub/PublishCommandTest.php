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

namespace Crest\Tests\Unit\Command\Stub;

use Crest\Command\Make\CommandCommand;
use Crest\Command\Stub\PublishCommand;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;

use function chdir;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function preg_match_all;
use function setlocale;
use function sort;
use function str_starts_with;
use function unlink;

use const LC_COLLATE;
use const PHP_EOL;

final class PublishCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('stub-publish', 'src/Action');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testACliProjectPublishesTheSharedStubs(): void
    {
        // cli has no stubs of its own yet. The shared folder still gives it
        // command.stub.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'cli'];\n");

        $status = $this->runCommand([]);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, Stub::SHARED, 'command'));
        $this->assertDirectoryDoesNotExist($this->root . '/resources/stubs/cli');
    }

    public function testAFlavorForAnArtifactStubIsRejected(): void
    {
        $status = $this->runCommand(['action', '--flavor', 'mvc']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            '--flavor is only for a project stub; in a project, the flavor comes from crest.php',
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/resources/stubs');
    }

    public function testAFlavorWithNoNameIsRejected(): void
    {
        $status = $this->runCommand(['--flavor', 'mvc']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            '--flavor is only for a project stub; in a project, the flavor comes from crest.php',
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/resources/stubs');
    }

    public function testAnAlreadyPublishedStubIsSkippedNotOverwritten(): void
    {
        $this->runCommand(['action']);

        $published = Stub::overridePath($this->root, 'adr', 'action');
        file_put_contents($published, 'edited');

        $status = $this->runCommand(['action']);

        // Exit 0, not 1: nothing failed. Clobbering an edited stub is what would
        // be the failure.
        $this->assertSame(0, $status);
        $this->assertSame('edited', (string) file_get_contents($published));
        $this->assertStringContainsString(
            'Skipped ' . $published . '; it exists already, pass --force to overwrite',
            $this->readStdout()
        );
        // The note is only for a shared copy. A flavor copy is the one that
        // the lookup uses.
        $this->assertStringNotContainsString('Note:', $this->readStdout());
    }

    public function testAnMvcProjectPublishesItsStubsAndTheSharedStubs(): void
    {
        // A fragment is published by name only, also the guidance of
        // make:controller.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'mvc'];\n");

        $status = $this->runCommand([]);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, 'mvc', 'controller'));
        $this->assertFileExists(Stub::overridePath($this->root, 'mvc', 'controller-base'));
        $this->assertFileExists(Stub::overridePath($this->root, Stub::SHARED, 'command'));
        $this->assertFileDoesNotExist(
            Stub::overridePath($this->root, 'mvc', 'fragment-guidance-controller')
        );
    }

    public function testAnUnknownFlavorForAProjectStubIsRejected(): void
    {
        $status = $this->runCommand(['project-front', '--flavor', 'cli']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("unknown flavor 'cli'; expected adr or mvc", $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/resources/stubs');
    }

    public function testAProjectStubGoesIntoTheDirectoryOptionAndNotAParentProject(): void
    {
        // `new --directory work` reads the overrides from work/. A crest.php
        // above it must not take the stub somewhere else.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn [];\n");
        mkdir($this->root . '/work');

        $status = $this->runThroughKernel(
            'stub:publish',
            PublishCommand::class,
            ['project-front', '--directory', $this->root . '/work']
        );

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root . '/work', 'adr', 'project-front'));
        $this->assertFileDoesNotExist(Stub::overridePath($this->root, 'adr', 'project-front'));
    }

    public function testAProjectStubGoesIntoTheWorkingDirectoryAndNotAParentProject(): void
    {
        // `new` with no --directory reads the overrides from the working
        // directory. endScratchProject() restores the working directory.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn [];\n");
        mkdir($this->root . '/work');
        chdir($this->root . '/work');

        $status = $this->runThroughKernel('stub:publish', PublishCommand::class, ['project-front']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root . '/work', 'adr', 'project-front'));
        $this->assertFileDoesNotExist(Stub::overridePath($this->root, 'adr', 'project-front'));
    }

    public function testAProjectStubIgnoresTheConfiguredFlavor(): void
    {
        // The flavor of a project stub is --flavor, adr by default, not the
        // flavor of crest.php: new reads the overrides before a project exists.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'mvc'];\n");

        $status = $this->runCommand(['project-front']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, 'adr', 'project-front'));
    }

    public function testAProjectStubMayBePublishedByName(): void
    {
        $status = $this->runCommand(['project-front']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, 'adr', 'project-front'));
    }

    public function testAProjectStubNeedsNoProjectConfiguration(): void
    {
        // The directory that `new` runs in is not a project.
        unlink($this->root . '/composer.json');

        $status = $this->runCommand(['project-front']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, 'adr', 'project-front'));
    }

    public function testAProjectStubOfTheMvcFlavorGoesIntoTheMvcFolder(): void
    {
        $status = $this->runCommand(['project-front', '--flavor', 'mvc']);

        $this->assertSame(0, $status);
        $this->assertSame(
            (string) file_get_contents(Stub::packagedPath(Paths::stubs(), 'mvc', 'project-front')),
            (string) file_get_contents(Stub::overridePath($this->root, 'mvc', 'project-front'))
        );
        $this->assertFileDoesNotExist(Stub::overridePath($this->root, 'adr', 'project-front'));
    }

    public function testAProjectStubThatNoFlavorHasGetsNoHint(): void
    {
        $status = $this->runCommand(['project-nope']);

        $this->assertSame(1, $status);
        $this->assertSame("crest: stub 'adr/project-nope' is not packaged" . PHP_EOL, $this->readStderr());
    }

    public function testAProjectStubThatTheFlavorDoesNotHaveIsNotPackaged(): void
    {
        // adr is the default, and only mvc has the views. The flavor of a
        // project stub comes from --flavor, so the error names the option.
        $status = $this->runCommand(['project-phtml-layout']);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: stub 'adr/project-phtml-layout' is not packaged; the mvc flavor has it: pass --flavor=mvc"
            . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testAPublishedSharedStubChangesTheOutputOfMakeCommand(): void
    {
        $this->runCommand(['command']);

        file_put_contents(
            Stub::overridePath($this->root, Stub::SHARED, 'command'),
            "<?php\n// edited {{ class }}\n"
        );

        $status = $this->runProjectCommand('make:command', CommandCommand::class, ['Greet']);

        $this->assertSame(0, $status);
        $this->assertSame(
            "<?php\n// edited GreetCommand\n",
            (string) file_get_contents($this->root . '/src/Command/GreetCommand.php')
        );
    }

    public function testAPublishedStubIsAByteForByteCopy(): void
    {
        $this->runCommand(['action']);

        $this->assertSame(
            (string) file_get_contents(Paths::stubs() . '/adr/action.stub'),
            (string) file_get_contents(
                Stub::overridePath($this->root, 'adr', 'action')
            )
        );
    }

    public function testAPublishedStubLandsWhereResolutionLooksForIt(): void
    {
        // The point of the whole command: an edited copy has to win. If publish
        // and resolve ever disagreed about the layout this would still pass file
        // checks while doing nothing useful.
        $this->runCommand(['action']);

        $published = Stub::overridePath($this->root, 'adr', 'action');
        file_put_contents($published, 'edited');

        $stub = new Stub(Paths::stubs(), $this->root);

        $this->assertSame($published, $stub->resolve('adr', 'action'));
        $this->assertSame('edited', $stub->render('adr', 'action', []));
    }

    public function testASharedCopyNamesTheFlavorCopyThatWins(): void
    {
        // A project that published command.stub before it moved to the shared
        // folder has a copy in adr/. That copy wins, so an edit of the new
        // shared copy has no effect. The note tells the user.
        mkdir($this->root . '/resources/stubs/adr', 0o775, true);
        file_put_contents(Stub::overridePath($this->root, 'adr', 'command'), 'old copy');

        $status = $this->runCommand(['command']);

        $stdout = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'Published ' . Stub::overridePath($this->root, Stub::SHARED, 'command'),
            $stdout
        );
        $this->assertStringContainsString(
            'Note: crest uses ' . Stub::overridePath($this->root, 'adr', 'command')
            . ', because the flavor folder comes first',
            $stdout
        );
    }

    public function testASharedFragmentMayBePublishedByName(): void
    {
        $status = $this->runCommand(['fragment-guidance-command']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, Stub::SHARED, 'fragment-guidance-command'));
    }

    public function testASharedProjectStubGoesIntoTheSharedFolder(): void
    {
        // The compose file is the same in each flavor, so its stub is shared.
        $status = $this->runCommand(['project-compose']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, Stub::SHARED, 'project-compose'));
        $this->assertFileDoesNotExist(Stub::overridePath($this->root, 'adr', 'project-compose'));
    }

    public function testASharedStubIsPublishedByNameIntoTheSharedFolder(): void
    {
        $status = $this->runCommand(['command']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, Stub::SHARED, 'command'));
        $this->assertFileDoesNotExist(Stub::overridePath($this->root, 'adr', 'command'));
        $this->assertStringNotContainsString('Note:', $this->readStdout());
    }

    public function testASharedStubIsSkippedNotOverwritten(): void
    {
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'mvc'];\n");

        $this->runCommand([]);

        $published = Stub::overridePath($this->root, Stub::SHARED, 'command');
        file_put_contents($published, 'edited');

        $status = $this->runCommand([]);

        $this->assertSame(0, $status);
        $this->assertSame('edited', (string) file_get_contents($published));
        $this->assertStringContainsString(
            'Skipped ' . $published . '; it exists already, pass --force to overwrite',
            $this->readStdout()
        );
    }

    public function testASingleStubMayBePublishedOnItsOwn(): void
    {
        $status = $this->runCommand(['action']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, 'adr', 'action'));
        $this->assertFileDoesNotExist(Stub::overridePath($this->root, 'adr', 'responder'));
    }

    public function testASkippedSharedCopyAlsoNamesTheFlavorCopyThatWins(): void
    {
        mkdir($this->root . '/resources/stubs/adr', 0o775, true);
        mkdir($this->root . '/resources/stubs/shared', 0o775, true);
        file_put_contents(Stub::overridePath($this->root, 'adr', 'command'), 'old copy');
        file_put_contents(Stub::overridePath($this->root, Stub::SHARED, 'command'), 'shared copy');

        $status = $this->runCommand(['command']);

        $stdout = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'Skipped ' . Stub::overridePath($this->root, Stub::SHARED, 'command'),
            $stdout
        );
        $this->assertStringContainsString(
            'Note: crest uses ' . Stub::overridePath($this->root, 'adr', 'command')
            . ', because the flavor folder comes first',
            $stdout
        );
    }

    public function testAStubNameThatIsAPathIsRejected(): void
    {
        // Without the guard this resolves through is_file() and copies a file in
        // from outside the package. No privilege is crossed - it is the
        // developer's own machine - but "is not packaged" would be a lie about
        // what went wrong.
        $status = $this->runCommand(['../../../etc/passwd']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'../../../etc/passwd' is not a stub name",
            $this->readStderr()
        );
    }

    public function testAStubNameWithATrailingNewlineIsRejected(): void
    {
        $status = $this->runCommand(["action\n"]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("'action\n' is not a stub name", $this->readStderr());
    }

    public function testAStubOfAnotherFlavorIsNotPackagedForThisOne(): void
    {
        // action is an ADR stub. An MVC project must not get a copy of it.
        // The flavor comes from crest.php, so the error has no --flavor hint.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'mvc'];\n");

        $status = $this->runCommand(['action']);

        $this->assertSame(1, $status);
        $this->assertSame("crest: stub 'mvc/action' is not packaged" . PHP_EOL, $this->readStderr());
    }

    public function testAStubThatIsNotPackagedIsReported(): void
    {
        $status = $this->runCommand(['nope']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("stub 'adr/nope' is not packaged", $this->readStderr());
    }

    public function testDefinitionNamesItselfStubPublish(): void
    {
        $this->assertSame('stub:publish', (new PublishCommand())->define()->getName());
    }

    public function testEveryPackagedAdrStubIsPublished(): void
    {
        // Asserted against the packaged directory rather than a hardcoded list,
        // so a stub added later is covered without touching this test - and a
        // stub that stops being published fails it.
        $status = $this->runCommand([]);

        $this->assertSame(0, $status);

        foreach ($this->packagedStubs() as $name => $folder) {
            $this->assertFileExists(
                Stub::overridePath($this->root, $folder, $name)
            );
        }
    }

    public function testForceOverwritesAPublishedStub(): void
    {
        $this->runCommand(['action']);

        $published = Stub::overridePath($this->root, 'adr', 'action');
        file_put_contents($published, 'edited');

        $status = $this->runCommand(['action', '--force']);

        $this->assertSame(0, $status);
        $this->assertSame(
            (string) file_get_contents(Paths::stubs() . '/adr/action.stub'),
            (string) file_get_contents($published)
        );
    }

    public function testFragmentStubsAreNotPublishedInBulk(): void
    {
        // A fragment holds a piece of the framework API or guidance text. A
        // publish with no name must not freeze copies of them in a project
        // without notice. A publish by name still copies one.
        $fragments = [];

        foreach ((new Stub(Paths::stubs()))->packagedFolders('adr') as $name => $folder) {
            if (true === str_starts_with($name, Stub::FRAGMENT_PREFIX)) {
                $fragments[$name] = $folder;
            }
        }

        $this->assertArrayHasKey('fragment-guidance-command', $fragments);

        $this->runCommand([]);

        foreach ($fragments as $name => $folder) {
            $this->assertFileDoesNotExist(Stub::overridePath($this->root, $folder, $name));
        }
    }

    public function testProjectStubsAreNotPublishedInBulk(): void
    {
        // They have an effect only in the directory that `crest new` puts the
        // project into. In a project they do nothing. The shared ones too.
        $projectStubs = [];

        foreach ((new Stub(Paths::stubs()))->packagedFolders('adr') as $name => $folder) {
            if (true === str_starts_with($name, Stub::PROJECT_PREFIX)) {
                $projectStubs[$name] = $folder;
            }
        }

        $this->assertArrayHasKey('project-compose', $projectStubs);

        $this->runCommand([]);

        foreach ($projectStubs as $name => $folder) {
            $this->assertFileDoesNotExist(Stub::overridePath($this->root, $folder, $name));
        }
    }

    public function testPublishedPathsAreReported(): void
    {
        $this->runCommand(['action']);

        $this->assertStringContainsString(
            'Published ' . Stub::overridePath($this->root, 'adr', 'action'),
            $this->readStdout()
        );
    }

    public function testPublishingContinuesPastAStubTheProjectAlreadyHas(): void
    {
        // A skipped stub must not end the run. `action-view` sorts before
        // `action`, so with the loop breaking instead of continuing everything
        // from `middleware` onwards would silently never be published.
        $this->runCommand(['action']);

        $this->runCommand([]);

        foreach ($this->packagedStubs() as $name => $folder) {
            $this->assertFileExists(
                Stub::overridePath($this->root, $folder, $name)
            );
        }
    }

    public function testStubsArePublishedInByteOrderOnEveryMachine(): void
    {
        // glob() sorts with the collation of the locale. Under en_US.UTF-8,
        // `action.stub` comes before `action-view.stub`; in byte order it is
        // the other way round. The listing must be the same on every machine.
        $previous = (string) setlocale(LC_COLLATE, '0');

        if (false === setlocale(LC_COLLATE, 'en_US.UTF-8', 'en_US.utf8')) {
            $this->markTestSkipped('the en_US.UTF-8 locale is not installed');
        }

        try {
            $this->runCommand([]);
        } finally {
            setlocale(LC_COLLATE, $previous);
        }

        // The folder and the whole file name: without `.stub`, `action` sorts
        // before `action-view` in byte order too, and the test would prove
        // nothing. The ADR stubs come first, then the shared ones.
        preg_match_all('#/((?:adr|shared)/[a-z-]+\.stub)$#m', $this->readStdout(), $matches);

        $published = $matches[1];
        $sorted    = $published;
        sort($sorted);

        $this->assertNotEmpty($published);
        $this->assertSame($sorted, $published);
    }

    public function testTheNoteForASharedProjectStubUsesTheFlavorOption(): void
    {
        // For an MVC project, a copy in the mvc folder wins over the shared
        // copy, so the note names it.
        $winner = Stub::overridePath($this->root, 'mvc', 'project-compose');

        mkdir(dirname($winner), 0o775, true);
        file_put_contents($winner, 'old copy');

        $status = $this->runCommand(['project-compose', '--flavor', 'mvc']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'Note: crest uses ' . $winner . ', because the flavor folder comes first',
            $this->readStdout()
        );
    }

    /**
     * The stubs a publish with no name copies in an ADR project, name =>
     * packaged folder: every ADR and shared stub, the ADR one on the same name,
     * except the project stubs and the fragment stubs.
     *
     * @return array<string, string>
     */
    private function packagedStubs(): array
    {
        $stubs = [];

        foreach ((new Stub(Paths::stubs()))->packagedFolders('adr') as $name => $folder) {
            if (
                true === str_starts_with($name, Stub::PROJECT_PREFIX)
                || true === str_starts_with($name, Stub::FRAGMENT_PREFIX)
            ) {
                continue;
            }

            $stubs[$name] = $folder;
        }

        return $stubs;
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('stub:publish', PublishCommand::class, $arguments);
    }
}
