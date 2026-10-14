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

namespace Crest\Tests\Unit\Generator\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Generator\Project\AdrSkeleton;
use Crest\Generator\Project\MvcSkeleton;
use Crest\Generator\Project\Skeletons;
use PHPUnit\Framework\TestCase;

final class SkeletonsTest extends TestCase
{
    public function testAdrHasASkeleton(): void
    {
        $this->assertInstanceOf(AdrSkeleton::class, Skeletons::for('adr'));
    }

    public function testAnUnknownFlavorIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown flavor 'cli'; expected adr or mvc");

        Skeletons::for('CLI');
    }

    public function testMvcHasASkeleton(): void
    {
        $this->assertInstanceOf(MvcSkeleton::class, Skeletons::for('mvc'));
    }

    public function testTheFlavorIsMadeLowercase(): void
    {
        $this->assertInstanceOf(AdrSkeleton::class, Skeletons::for('ADR'));
    }

    public function testTheFlavorsAreListed(): void
    {
        $this->assertSame(['adr', 'mvc'], Skeletons::flavors());
    }
}
