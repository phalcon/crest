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

use Crest\Project\Locator;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;

final class LocatorTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('locator', 'src/Action/Deep');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    public function testFindsTheFileInTheStartingDirectory(): void
    {
        file_put_contents($this->root . '/crest.php', '<?php return [];');

        $this->assertSame($this->root . '/crest.php', Locator::locate($this->root));
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

    public function testReturnsNullOnceTheFilesystemRootIsPassed(): void
    {
        // Nothing is written, so the walk runs all the way to '/' and has to
        // stop there rather than looping forever.
        $this->assertNull(Locator::locate($this->root . '/src/Action/Deep'));
    }
}
