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

namespace Crest\Tests\Unit\Generator;

use Crest\Console\Exceptions\Exception;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\Outcome;
use Crest\Generator\Stub;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function is_readable;
use function mkdir;

final class ArtifactWriterTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('artifact-writer', 'packaged/adr');

        file_put_contents($this->root . '/packaged/adr/thing.stub', 'hello {{ class }}');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    public function testADirectoryInThePlaceOfTheTargetIsNotMistakenForAnExistingFile(): void
    {
        // is_file() is the guard, not file_exists(): the latter is true of a
        // directory too, which would report "already exists" and then fail to
        // write - the wrong message for the wrong reason.
        $file = $this->root . '/out/Thing.php';
        mkdir($file, 0o775, true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('could not write ' . $file);

        $this->writer()->render($file, 'thing', ['class' => 'Thing'], false);
    }

    public function testAnUncreatableDirectoryIsReported(): void
    {
        // A plain file where the directory has to go. mkdir() cannot succeed,
        // whoever is running - unlike a chmod, which root ignores.
        file_put_contents($this->root . '/blocked', 'not a directory');

        $file = $this->root . '/blocked/Thing.php';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('could not create ' . $this->root . '/blocked');

        $this->writer()->render($file, 'thing', ['class' => 'Thing'], false);
    }

    public function testRenderCreatesMissingDirectoriesAllTheWayDown(): void
    {
        $file = $this->root . '/a/b/c/Thing.php';

        $this->writer()->render($file, 'thing', ['class' => 'Thing'], false);

        $this->assertFileExists($file);
    }

    public function testRenderOnceCreatesAMissingFile(): void
    {
        $file = $this->root . '/out/Thing.php';

        $this->assertSame(Outcome::Created, $this->writer()->renderOnce($file, 'thing', ['class' => 'Thing'], false));
        $this->assertSame('hello Thing', (string) file_get_contents($file));
    }

    public function testRenderOnceKeepsAnExistingFile(): void
    {
        // After the first write, the developer owns the file.
        $file = $this->root . '/out/Thing.php';

        $this->writer()->renderOnce($file, 'thing', ['class' => 'First'], false);

        $this->assertSame(Outcome::Kept, $this->writer()->renderOnce($file, 'thing', ['class' => 'Second'], false));
        $this->assertSame('hello First', (string) file_get_contents($file));
    }

    public function testRenderOnceReplacesAnExistingFileWhenForced(): void
    {
        $file = $this->root . '/out/Thing.php';

        $this->writer()->renderOnce($file, 'thing', ['class' => 'First'], false);

        $this->assertSame(Outcome::Created, $this->writer()->renderOnce($file, 'thing', ['class' => 'Second'], true));
        $this->assertSame('hello Second', (string) file_get_contents($file));
    }

    public function testRenderOverwritesWhenForced(): void
    {
        $file = $this->root . '/out/Thing.php';

        $this->writer()->render($file, 'thing', ['class' => 'First'], false);
        $this->writer()->render($file, 'thing', ['class' => 'Second'], true);

        $this->assertSame('hello Second', (string) file_get_contents($file));
    }

    public function testRenderOwnedCreatesAMissingFile(): void
    {
        $file = $this->root . '/out/Thing.php';

        $this->assertSame(Outcome::Created, $this->writer()->renderOwned($file, 'thing', ['class' => 'Thing']));
        $this->assertSame('hello Thing', (string) file_get_contents($file));
    }

    public function testRenderOwnedKeepsADifferentFileWithoutTheMarker(): void
    {
        // A file without the marker belongs to the developer.
        $file = $this->root . '/out/Thing.php';
        mkdir($this->root . '/out', 0o775, true);
        file_put_contents($file, 'mine');

        $this->assertSame(Outcome::Kept, $this->writer()->renderOwned($file, 'thing', ['class' => 'Thing']));
        $this->assertSame('mine', (string) file_get_contents($file));
    }

    public function testRenderOwnedLeavesTheSameContentUnchanged(): void
    {
        // The stub of this test has no marker. The same content is Unchanged
        // and not Kept, because the comparison comes first.
        $file = $this->root . '/out/Thing.php';

        $this->writer()->renderOwned($file, 'thing', ['class' => 'Thing']);

        $this->assertSame(Outcome::Unchanged, $this->writer()->renderOwned($file, 'thing', ['class' => 'Thing']));
        $this->assertSame('hello Thing', (string) file_get_contents($file));
    }

    public function testRenderOwnedReportsAFileItCouldNotRead(): void
    {
        $file = $this->root . '/out/Thing.php';
        mkdir($this->root . '/out', 0o775, true);
        file_put_contents($file, 'mine');
        chmod($file, 0o000);

        // root can read a file with mode 000, so the test proves nothing then.
        if (true === is_readable($file)) {
            $this->markTestSkipped('this user can read a file with mode 000');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('could not read ' . $file);

        $this->writer()->renderOwned($file, 'thing', ['class' => 'Thing']);
    }

    public function testRenderOwnedUpdatesAFileWithTheMarker(): void
    {
        $file = $this->root . '/out/Thing.php';
        mkdir($this->root . '/out', 0o775, true);
        file_put_contents($file, 'old ' . ArtifactWriter::MARKER);

        $this->assertSame(Outcome::Updated, $this->writer()->renderOwned($file, 'thing', ['class' => 'Thing']));
        $this->assertSame('hello Thing', (string) file_get_contents($file));
    }

    public function testRenderRefusesToOverwriteWithoutForce(): void
    {
        $file = $this->root . '/out/Thing.php';

        $this->writer()->render($file, 'thing', ['class' => 'First'], false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($file . ' already exists; pass --force to overwrite');

        $this->writer()->render($file, 'thing', ['class' => 'Second'], false);
    }

    public function testRenderWritesTheSubstitutedStub(): void
    {
        $file = $this->root . '/out/Thing.php';

        $this->writer()->render($file, 'thing', ['class' => 'Thing'], false);

        $this->assertSame('hello Thing', (string) file_get_contents($file));
    }

    public function testWriteReportsAFileItCouldNotWrite(): void
    {
        // Straight at the static entry point, which stub:publish uses.
        $file = $this->root . '/out/Thing.php';
        mkdir($file, 0o775, true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('could not write ' . $file);

        ArtifactWriter::write($file, 'contents');
    }

    private function writer(): ArtifactWriter
    {
        return new ArtifactWriter(new Stub($this->root . '/packaged'), 'adr');
    }
}
