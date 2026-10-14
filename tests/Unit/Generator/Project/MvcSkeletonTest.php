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

use Crest\Generator\Project\Answers;
use Crest\Generator\Project\MvcSkeleton;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Flavor;
use Crest\Project\Runtime;
use PHPUnit\Framework\TestCase;

final class MvcSkeletonTest extends TestCase
{
    public function testAnEngineGivesItsOwnStubsAndExtension(): void
    {
        // The engine stubs are project-<engine>-<part>. A second engine gets
        // its own stubs and its own file extension.
        $this->assertSame(
            [
                'project-provider-dispatcher' => 'src/Providers/DispatcherProvider.php',
                'project-volt-provider'       => 'src/Providers/ViewProvider.php',
                'project-volt-layout'         => 'themes/default/index.volt',
                'project-volt-index'          => 'themes/default/index/index.volt',
            ],
            (new MvcSkeleton())->files($this->answers('volt'))
        );
    }

    public function testItHasThePhtmlEngine(): void
    {
        $this->assertSame(['phtml'], (new MvcSkeleton())->engines()->names());
    }

    public function testItIsTheMvcFlavor(): void
    {
        $this->assertSame(Flavor::MVC, (new MvcSkeleton())->flavor());
    }

    public function testTheEntryIsTheIndexController(): void
    {
        $this->assertSame('src/Controllers/IndexController.php', (new MvcSkeleton())->entry());
    }

    public function testTheFilesAreTheProvidersAndTheViews(): void
    {
        $this->assertSame(
            [
                'project-provider-dispatcher' => 'src/Providers/DispatcherProvider.php',
                'project-phtml-provider'      => 'src/Providers/ViewProvider.php',
                'project-phtml-layout'        => 'themes/default/index.phtml',
                'project-phtml-index'         => 'themes/default/index/index.phtml',
            ],
            (new MvcSkeleton())->files($this->answers('phtml'))
        );
    }

    public function testTheFilesUseTheDefaultEngineWithoutAnAnswer(): void
    {
        $this->assertSame(
            (new MvcSkeleton())->files($this->answers('phtml')),
            (new MvcSkeleton())->files($this->answers(null))
        );
    }

    public function testTheSeedIsTheBaseAndTheIndexController(): void
    {
        $stub = new Stub(Paths::stubs());

        $this->assertSame(
            [
                'src/Controllers/Base/CrestControllerBase.php' => $stub->render(
                    'mvc',
                    'controller-base',
                    ['class' => 'CrestControllerBase', 'namespace' => 'Acme\\Shop\\Controllers\\Base']
                ),
                'src/Controllers/IndexController.php' => $stub->render(
                    'mvc',
                    'controller',
                    [
                        'base'          => 'CrestControllerBase',
                        'baseNamespace' => 'Acme\\Shop\\Controllers\\Base',
                        'class'         => 'IndexController',
                        'namespace'     => 'Acme\\Shop\\Controllers',
                    ]
                ),
            ],
            (new MvcSkeleton())->seed($stub, $this->answers('phtml'))
        );
    }

    public function testTheValuesNameTheControllersTheProvidersAndTheViews(): void
    {
        $this->assertSame(
            [
                'baseFolder'          => 'Base',
                'controllerNamespace' => 'Acme\\Shop\\Controllers',
                'controllerPath'      => 'src/Controllers',
                'providerNamespace'   => 'Acme\\Shop\\Providers',
                'providerPath'        => 'src/Providers',
                'seed'                => 'IndexController',
                'viewsPath'           => 'themes/default',
            ],
            (new MvcSkeleton())->values($this->answers('phtml'))
        );
    }

    private function answers(?string $engine): Answers
    {
        return new Answers('shop', Flavor::MVC, 'Acme\\Shop', '8.4', 'v5', Runtime::docker(), $engine);
    }
}
