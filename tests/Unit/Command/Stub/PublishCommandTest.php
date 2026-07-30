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

use Crest\Command\Stub\PublishCommand;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function glob;

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

        foreach ($this->packagedStubs() as $name) {
            $this->assertFileExists(
                Stub::overridePath($this->root, 'adr', $name)
            );
        }
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

    public function testASingleStubMayBePublishedOnItsOwn(): void
    {
        $status = $this->runCommand(['action']);

        $this->assertSame(0, $status);
        $this->assertFileExists(Stub::overridePath($this->root, 'adr', 'action'));
        $this->assertFileDoesNotExist(Stub::overridePath($this->root, 'adr', 'responder'));
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
    }

    public function testPublishingContinuesPastAStubTheProjectAlreadyHas(): void
    {
        // A skipped stub must not end the run. `action-view` sorts before
        // `action`, so with the loop breaking instead of continuing everything
        // from `command` onwards would silently never be published.
        $this->runCommand(['action']);

        $this->runCommand([]);

        foreach ($this->packagedStubs() as $name) {
            $this->assertFileExists(
                Stub::overridePath($this->root, 'adr', $name)
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

    public function testAStubThatIsNotPackagedIsReported(): void
    {
        $status = $this->runCommand(['nope']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("stub 'adr/nope' is not packaged", $this->readStderr());
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

    public function testAFlavorWithNoPackagedStubsIsReported(): void
    {
        // cli ships nothing yet. Publishing silently and printing no lines would
        // read as success.
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'cli'];\n");

        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "no stubs are packaged for the 'cli' flavor",
            $this->readStderr()
        );
    }

    public function testPublishedPathsAreReported(): void
    {
        $this->runCommand(['action']);

        $this->assertStringContainsString(
            'Published ' . Stub::overridePath($this->root, 'adr', 'action'),
            $this->readStdout()
        );
    }

    /**
     * @return list<string>
     */
    private function packagedStubs(): array
    {
        $names = [];

        foreach (glob(Paths::stubs() . '/adr/*.stub') ?: [] as $path) {
            $names[] = basename($path, '.stub');
        }

        return $names;
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('stub:publish', PublishCommand::class, $arguments);
    }
}
