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

namespace Crest\Tests\Unit;

use Crest\Paths;
use PHPUnit\Framework\TestCase;

use function dirname;
use function is_dir;
use function is_file;

final class PathsTest extends TestCase
{
    public function testRootIsThePackageRoot(): void
    {
        // Measured from this test file rather than from Paths, so a wrong
        // depth literal inside Paths shows up here.
        $this->assertSame(dirname(__DIR__, 2), Paths::root());
        $this->assertTrue(is_file(Paths::root() . '/composer.json'));
    }

    public function testStubsPointsAtTheShippedStubDirectory(): void
    {
        $this->assertSame(Paths::root() . '/resources/stubs', Paths::stubs());
        $this->assertTrue(is_dir(Paths::stubs()));
        $this->assertTrue(is_file(Paths::stubs() . '/adr/action.stub'));
    }
}
