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
use Crest\Generator\Project\DatabaseChoice;
use Crest\Generator\Project\MvcSkeleton;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Flavor;
use Crest\Project\Runtime;
use PHPUnit\Framework\TestCase;

final class MvcSkeletonTest extends TestCase
{
    public function testADatabaseAddsItsProvider(): void
    {
        $this->assertSame(
            [
                'project-provider-dispatcher' => 'src/Providers/DispatcherProvider.php',
                'project-provider-url'        => 'src/Providers/UrlProvider.php',
                'project-phtml-provider'      => 'src/Providers/ViewProvider.php',
                'project-phtml-layout'        => 'themes/default/index.phtml',
                'project-phtml-index'         => 'themes/default/index/index.phtml',
                'project-provider-db'         => 'src/Providers/DbProvider.php',
            ],
            (new MvcSkeleton())->files($this->answers('phtml', DatabaseChoice::PGSQL))
        );
        $this->assertSame(
            'src/Providers/DbProvider.php',
            (new MvcSkeleton())->files($this->answers('phtml', DatabaseChoice::SQLITE))['project-provider-db-sqlite']
        );
    }

    public function testADatabaseGivesTheFrontItsProvider(): void
    {
        $values = (new MvcSkeleton())->values($this->answers('phtml', DatabaseChoice::MYSQL));

        $this->assertSame("use Acme\\Shop\\Providers\\DbProvider;\n", $values['dbUse']);
        $this->assertSame("            new DbProvider(),\n", $values['dbProvider']);
        // The SQLite file is relative to the project root.
        $this->assertSame(
            "            new DbProvider(\$this->projectRoot),\n",
            (new MvcSkeleton())->values($this->answers('phtml', DatabaseChoice::SQLITE))['dbProvider']
        );
    }

    public function testAnEngineGivesItsOwnStubsAndExtension(): void
    {
        // The engine stubs are project-<engine>-<part>. A second engine gets
        // its own stubs and its own file extension.
        $this->assertSame(
            [
                'project-provider-dispatcher' => 'src/Providers/DispatcherProvider.php',
                'project-provider-url'        => 'src/Providers/UrlProvider.php',
                'project-volt-provider'       => 'src/Providers/ViewProvider.php',
                'project-volt-layout'         => 'themes/default/index.volt',
                'project-volt-index'          => 'themes/default/index/index.volt',
            ],
            (new MvcSkeleton())->files($this->answers('volt'))
        );
    }

    public function testItHasThePhtmlEngineAndNoViews(): void
    {
        $this->assertSame(['phtml', 'volt', 'none'], (new MvcSkeleton())->engines()->names());
    }

    public function testItIsTheMvcFlavor(): void
    {
        $this->assertSame(Flavor::MVC, (new MvcSkeleton())->flavor());
    }

    public function testNoViewsGivesNoViewFiles(): void
    {
        $this->assertSame(
            [
                'project-provider-dispatcher' => 'src/Providers/DispatcherProvider.php',
                'project-provider-url'        => 'src/Providers/UrlProvider.php',
                'project-provider-db'         => 'src/Providers/DbProvider.php',
            ],
            (new MvcSkeleton())->files($this->answers('none', DatabaseChoice::MYSQL))
        );
    }

    public function testNoViewsGivesNoViewLinesAndTurnsTheImplicitViewOff(): void
    {
        $values = (new MvcSkeleton())->values($this->answers('none'));

        $this->assertSame('->useImplicitView(false)', $values['implicitView']);
        $this->assertSame('', $values['viewProvider']);
        $this->assertSame('', $values['viewUse']);
        $this->assertSame('', $values['viewsRow']);
    }

    public function testNoViewsGivesTheIndexControllerThatReturnsJson(): void
    {
        $stub = new Stub(Paths::stubs());

        $this->assertSame(
            $stub->render(
                'mvc',
                'project-none-controller',
                [
                    'base'          => 'CrestControllerBase',
                    'baseNamespace' => 'Acme\\Shop\\Controllers\\Base',
                    'class'         => 'IndexController',
                    'namespace'     => 'Acme\\Shop\\Controllers',
                ]
            ),
            (new MvcSkeleton())->seed($stub, $this->answers('none'))['src/Controllers/IndexController.php']
        );
    }

    public function testPhqlIsOnlyForV6WithADatabase(): void
    {
        $skeleton = new MvcSkeleton();

        $this->assertSame(
            ",\n        \"phalcon/phql\": \"1.0.x-dev\"",
            $skeleton->values($this->answers('phtml', DatabaseChoice::MYSQL, 'v6'))['phql']
        );
        // The extension (v5) has PHQL. A project without a database has no
        // models yet.
        $this->assertSame('', $skeleton->values($this->answers('phtml', DatabaseChoice::MYSQL))['phql']);
        $this->assertSame('', $skeleton->values($this->answers('phtml', DatabaseChoice::NONE, 'v6'))['phql']);
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
                'project-provider-url'        => 'src/Providers/UrlProvider.php',
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
                'dbProvider'          => '',
                'dbUse'               => '',
                'implicitView'        => '',
                'phql'                => '',
                'providerNamespace'   => 'Acme\\Shop\\Providers',
                'providerPath'        => 'src/Providers',
                'seed'                => 'IndexController',
                'viewProvider'        => "            new ViewProvider(\$this->projectRoot . '/themes/default'),\n",
                'viewUse'             => "use Acme\\Shop\\Providers\\ViewProvider;\n",
                'viewsPath'           => 'themes/default',
                'viewsRow'            => '| `themes/default/` | The views. `GET /` shows the view `index/index` in the '
                    . "layout `index` |\n",
                'volt'                => '',
                'voltCache'           => 'var/cache/volt',
            ],
            (new MvcSkeleton())->values($this->answers('phtml'))
        );
    }

    public function testVoltGivesItsViewsItsCacheAndThePackageForV6(): void
    {
        $skeleton = new MvcSkeleton();

        $this->assertSame(
            [
                'project-provider-dispatcher' => 'src/Providers/DispatcherProvider.php',
                'project-provider-url'        => 'src/Providers/UrlProvider.php',
                'project-volt-provider'       => 'src/Providers/ViewProvider.php',
                'project-volt-layout'         => 'themes/default/index.volt',
                'project-volt-index'          => 'themes/default/index/index.volt',
            ],
            $skeleton->files($this->answers('volt'))
        );
        $this->assertSame(
            "*\n!.gitignore\n",
            $skeleton->seed(new Stub(Paths::stubs()), $this->answers('volt'))['var/cache/volt/.gitignore'] ?? null
        );
        $this->assertArrayNotHasKey(
            'var/cache/volt/.gitignore',
            $skeleton->seed(new Stub(Paths::stubs()), $this->answers('phtml'))
        );

        $values = $skeleton->values($this->answers('volt', DatabaseChoice::NONE, 'v6'));

        $this->assertSame(",\n        \"phalcon/volt\": \"^1.0\"", $values['volt']);
        $this->assertSame(
            "            new ViewProvider(\$this->projectRoot . '/themes/default', "
            . "\$this->projectRoot . '/var/cache/volt'),\n",
            $values['viewProvider']
        );
        // The extension (v5) has the Volt parser.
        $this->assertSame('', $skeleton->values($this->answers('volt'))['volt']);
    }

    private function answers(
        ?string $engine,
        DatabaseChoice $database = DatabaseChoice::NONE,
        string $phalcon = 'v5'
    ): Answers {
        return new Answers('shop', Flavor::MVC, 'Acme\\Shop', '8.4', $phalcon, Runtime::docker(), $engine, $database);
    }
}
