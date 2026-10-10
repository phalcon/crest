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

use Crest\Project\Config;
use Crest\Project\Flavor;
use Crest\Project\Layout;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    public function testTheDefaultPathsAreInTheSourceFolder(): void
    {
        foreach (Config::defaultPaths(Flavor::ADR) as $path) {
            $this->assertStringStartsWith(Layout::SOURCE . '/', $path);
        }
    }

    public function testTheLayoutOfANewProject(): void
    {
        $this->assertSame(
            ['vendor/autoload.php', 'public', '.env', 'AppFront', 8080, 'APP_PORT', '.htrouter.php', 'src'],
            [
                Layout::AUTOLOADER,
                Layout::DOCUMENT_ROOT,
                Layout::ENV_FILE,
                Layout::FRONT,
                Layout::PORT,
                Layout::PORT_VARIABLE,
                Layout::ROUTER,
                Layout::SOURCE,
            ]
        );
    }
}
