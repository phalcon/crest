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

use Crest\Generator\Placement;
use PHPUnit\Framework\TestCase;

final class PlacementTest extends TestCase
{
    public function testInPutsTheClassFileInTheFolder(): void
    {
        $placement = Placement::in(
            '/srv/app/src/Modules/Admin/Controllers',
            'App\\Modules\\Admin\\Controllers',
            'UsersController'
        );

        $this->assertSame('UsersController', $placement->class);
        $this->assertSame('/srv/app/src/Modules/Admin/Controllers/UsersController.php', $placement->file);
        $this->assertSame('App\\Modules\\Admin\\Controllers', $placement->namespace);
    }
}
