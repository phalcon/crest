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

namespace Crest\Tests\Unit\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Project\Locator;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chdir;
use function file_put_contents;
use function getcwd;

final class LocatorTest extends TestCase
{
    use ScratchDirectory;

    private string $previousCwd = '';

    protected function setUp(): void
    {
        $this->makeScratchDirectory('locator', 'src/Action/Deep');
        $this->previousCwd = (string) getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        $this->removeScratchDirectory();
    }

    public function testFindsTheFileInTheStartingDirectory(): void
    {
        file_put_contents($this->root . '/crest.php', '<?php return [];');

        $this->assertSame($this->root . '/crest.php', Locator::locate($this->root));
    }

    public function testProjectIsNullWhenNoDirectoryHasAComposerJson(): void
    {
        // From the filesystem root: a walk from the scratch directory finds
        // the composer.json of this repository.
        $this->assertNull(Locator::project('/'));
    }

    public function testProjectIsTheNearestDirectoryWithAComposerJson(): void
    {
        file_put_contents($this->root . '/composer.json', '{}');
        file_put_contents($this->root . '/src/composer.json', '{}');

        $this->assertSame($this->root . '/src', Locator::project($this->root . '/src/Action/Deep'));
    }

    public function testProjectWalksUpToTheComposerJson(): void
    {
        file_put_contents($this->root . '/composer.json', '{}');

        $this->assertSame($this->root, Locator::project($this->root . '/src/Action/Deep'));
    }

    public function testReturnsNullOnceTheFilesystemRootIsPassed(): void
    {
        // Nothing is written, so the walk runs all the way to '/' and has to
        // stop there rather than looping forever.
        $this->assertNull(Locator::locate($this->root . '/src/Action/Deep'));
    }

    public function testStartIsTheWorkingDirectoryWhenEmpty(): void
    {
        chdir($this->root . '/src');

        $this->assertSame($this->root . '/src', Locator::start(''));
    }

    public function testStartIsTheWorkingDirectoryWhenNull(): void
    {
        chdir($this->root . '/src');

        $this->assertSame($this->root . '/src', Locator::start(null));
    }

    public function testStartRefusesAFile(): void
    {
        file_put_contents($this->root . '/file', '');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($this->root . '/file is not a directory');

        Locator::start($this->root . '/file');
    }

    public function testStartRefusesAMissingDirectory(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing-folder is not a directory');

        Locator::start('missing-folder');
    }

    public function testStartResolvesARelativeDirectory(): void
    {
        // A walk from `..` as a string reaches `.`, the working directory.
        chdir($this->root . '/src/Action');

        $this->assertSame($this->root . '/src', Locator::start('..'));
    }

    public function testWalksUpUntilItFindsTheFile(): void
    {
        // The whole point of the walk: crest has to work from anywhere inside
        // a project, not only from its root.
        file_put_contents($this->root . '/crest.php', '<?php return [];');

        $this->assertSame(
            $this->root . '/crest.php',
            Locator::locate($this->root . '/src/Action/Deep')
        );
    }
}
