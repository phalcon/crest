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

namespace Crest\Tests\Unit\Console;

use Crest\Console\PackageVersion;
use PHPUnit\Framework\TestCase;

final class PackageVersionTest extends TestCase
{
    public function testAbsentPackageIsNotInstalled(): void
    {
        $this->assertFalse(PackageVersion::isInstalled('nope/nope'));
    }

    public function testAbsentPackageReportsTheUnknownPlaceholder(): void
    {
        $this->assertSame(PackageVersion::UNKNOWN, PackageVersion::of('nope/nope'));
    }

    public function testInstalledPackageIsReported(): void
    {
        $this->assertTrue(PackageVersion::isInstalled('phpunit/phpunit'));
    }

    public function testInstalledPackageYieldsSomethingOtherThanThePlaceholder(): void
    {
        $version = PackageVersion::of('phpunit/phpunit');

        $this->assertNotSame(PackageVersion::UNKNOWN, $version);
        $this->assertNotSame('', $version);
    }
}
