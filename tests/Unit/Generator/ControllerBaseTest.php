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

use Crest\Generator\ControllerBase;
use PHPUnit\Framework\TestCase;

final class ControllerBaseTest extends TestCase
{
    public function testTheFileIsInTheBaseFolderOfTheControllerPath(): void
    {
        $this->assertSame(
            '/srv/app/src/Controllers/Base/CrestControllerBase.php',
            ControllerBase::file('/srv/app/src/Controllers')
        );
    }

    public function testTheNamesAreFixed(): void
    {
        // make:controller and `new` write the same file. A later version of
        // crest must find it there.
        $this->assertSame('Base', ControllerBase::FOLDER);
        $this->assertSame('CrestControllerBase', ControllerBase::NAME);
    }

    public function testTheNamespaceIsTheBaseFolderOfTheControllerNamespace(): void
    {
        $this->assertSame(
            'Acme\\Shop\\Controllers\\Base',
            ControllerBase::namespace('Acme\\Shop\\Controllers')
        );
    }
}
