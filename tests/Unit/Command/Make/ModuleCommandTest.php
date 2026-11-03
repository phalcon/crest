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

namespace Crest\Tests\Unit\Command\Make;

use Crest\Command\Make\ModuleCommand;
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function file_get_contents;
use function file_put_contents;
use function mkdir;

use const PHP_EOL;

/**
 * make:module writes Module.php and the folders of the module, and prints how
 * to register it (spec 3.3 to 3.5).
 */
final class ModuleCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    private const MODULE = '/src/Modules/Admin/Module.php';

    protected function setUp(): void
    {
        $this->startScratchProject('make-module');
        $this->writeCrestPhp("['flavor' => 'mvc']");
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableNames(): iterable
    {
        yield 'a space' => ['my module'];
        yield 'a first digit' => ['9admin'];
        yield 'a slash' => ['admin/users'];
        yield 'a hyphen' => ['admin-panel'];
    }

    public function testACliProjectGetsTheFlavorError(): void
    {
        $this->writeCrestPhp("['flavor' => 'cli']");

        $status = $this->runCommand(['admin']);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: make:module does not support the 'cli' flavor of this project; it supports: mvc" . PHP_EOL,
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/src/Modules');
    }

    public function testAModulePathOutsidePsr4StopsTheCommand(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc', 'paths' => ['module' => 'lib/Modules']]");

        $status = $this->runCommand(['admin']);

        $this->assertSame(1, $status);
        $this->assertSame("crest: no psr-4 autoload entry covers 'lib/Modules'" . PHP_EOL, $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/lib');
    }

    public function testAnAdrProjectGetsTheFlavorError(): void
    {
        $this->writeCrestPhp();

        $status = $this->runCommand(['admin']);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: make:module does not support the 'adr' flavor of this project; it supports: mvc" . PHP_EOL,
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/src/Modules');
    }

    public function testAnExistingFolderIsUsed(): void
    {
        // The key adminpanel finds the folder AdminPanel. crest does not
        // make a second folder Adminpanel.
        mkdir($this->root . '/src/Modules/AdminPanel', 0o775, true);

        $status = $this->runCommand(['adminpanel']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "namespace App\\Modules\\AdminPanel;\n",
            (string) file_get_contents($this->root . '/src/Modules/AdminPanel/Module.php')
        );
        $this->assertStringStartsWith(
            'Created ' . $this->root . '/src/Modules/AdminPanel/Module.php' . PHP_EOL
            . 'Created ' . $this->root . '/src/Modules/AdminPanel/Controllers' . PHP_EOL
            . 'Created ' . $this->root . '/src/Modules/AdminPanel/Models' . PHP_EOL
            . 'Created ' . $this->root . '/src/Modules/AdminPanel/views' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/src/Modules/Adminpanel');
    }

    public function testAnExistingModuleStops(): void
    {
        // ADMIN has the key of admin, so it finds the folder Admin.
        $this->runCommand(['admin']);
        $this->freshStreams();

        $status = $this->runCommand(['ADMIN']);

        $this->assertSame(1, $status);
        $this->assertSame(
            'crest: ' . $this->root . self::MODULE . ' already exists; pass --force to overwrite' . PHP_EOL,
            $this->readStderr()
        );
    }

    public function testAnUnderscoreNameGivesAStudlyFolderAndALowercaseKey(): void
    {
        $status = $this->runCommand(['admin_panel']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "namespace App\\Modules\\AdminPanel;\n",
            (string) file_get_contents($this->root . '/src/Modules/AdminPanel/Module.php')
        );
        $this->assertStringContainsString('--module=adminpanel', $this->readStdout());
    }

    /**
     * @dataProvider unusableNames
     */
    public function testAnUnusableNameStopsAndWritesNothing(string $name): void
    {
        $status = $this->runCommand([$name]);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: '" . $name . "' is not a usable module name; expected a single name like 'admin'" . PHP_EOL,
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/src/Modules');
    }

    public function testAnUppercaseNameGivesTheSameModule(): void
    {
        $status = $this->runCommand(['Admin']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . self::MODULE);
        $this->assertStringContainsString("        'admin' => ['className' =>", $this->readStdout());
    }

    public function testDefinitionNamesItself(): void
    {
        $definition = (new ModuleCommand())->define();

        $this->assertSame('make:module', $definition->getName());
        $this->assertSame('Create an MVC module', $definition->getDescription());
    }

    public function testForceReplacesOnlyTheModuleClass(): void
    {
        // The folders exist, so they get no Created line, and the files in
        // them stay.
        $this->runCommand(['admin']);
        file_put_contents($this->root . self::MODULE, 'mine');
        file_put_contents($this->root . '/src/Modules/Admin/Controllers/Mine.php', 'mine');
        $this->freshStreams();

        $status = $this->runCommand(['admin', '--force']);

        $this->assertSame(0, $status);
        $this->assertSame($this->wholeModule(), (string) file_get_contents($this->root . self::MODULE));
        $this->assertSame(
            'mine',
            (string) file_get_contents($this->root . '/src/Modules/Admin/Controllers/Mine.php')
        );
        $this->assertStringStartsWith(
            'Created ' . $this->root . self::MODULE . PHP_EOL . 'Nothing registers it yet.',
            $this->readStdout()
        );
    }

    public function testNameArgumentIsRequired(): void
    {
        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'name'", $this->readStderr());
    }

    public function testTheFirstRunWritesTheModuleTheFoldersAndTheGuidance(): void
    {
        // Asserted whole: the guidance is the wiring that the developer
        // copies, and the blank lines around the snippets are part of it.
        $status = $this->runCommand(['admin']);

        $expected = 'Created ' . $this->root . self::MODULE . PHP_EOL
            . 'Created ' . $this->root . '/src/Modules/Admin/Controllers' . PHP_EOL
            . 'Created ' . $this->root . '/src/Modules/Admin/Models' . PHP_EOL
            . 'Created ' . $this->root . '/src/Modules/Admin/views' . PHP_EOL
            . 'Nothing registers it yet. In run() of your front controller, register the' . PHP_EOL
            . 'module before handle():' . PHP_EOL
            . PHP_EOL
            . '    $application = new Application($this->boot());' . PHP_EOL
            . '    $application->registerModules([' . PHP_EOL
            . "        'admin' => ['className' => \\App\\Modules\\Admin\\Module::class]," . PHP_EOL
            . '    ]);' . PHP_EOL
            . '    $response = $application->handle($uri);' . PHP_EOL
            . PHP_EOL
            . 'Then add the routes of the module to the router, for example:' . PHP_EOL
            . PHP_EOL
            . "    \$router->add('/admin', [" . PHP_EOL
            . "        'module'     => 'admin'," . PHP_EOL
            . "        'controller' => 'index'," . PHP_EOL
            . "        'action'     => 'index'," . PHP_EOL
            . '    ]);' . PHP_EOL
            . "    \$router->add('/admin/:controller', [" . PHP_EOL
            . "        'module'     => 'admin'," . PHP_EOL
            . "        'controller' => 1," . PHP_EOL
            . "        'action'     => 'index'," . PHP_EOL
            . '    ]);' . PHP_EOL
            . "    \$router->add('/admin/:controller/:action/:params', [" . PHP_EOL
            . "        'module'     => 'admin'," . PHP_EOL
            . "        'controller' => 1," . PHP_EOL
            . "        'action'     => 2," . PHP_EOL
            . "        'params'     => 3," . PHP_EOL
            . '    ]);' . PHP_EOL
            . PHP_EOL
            . 'Write its first controller with: crest make:controller Index --module=admin' . PHP_EOL;

        $this->assertSame(0, $status);
        $this->assertSame($expected, $this->readStdout());
        $this->assertDirectoryExists($this->root . '/src/Modules/Admin/Controllers');
        $this->assertDirectoryExists($this->root . '/src/Modules/Admin/Models');
        $this->assertDirectoryExists($this->root . '/src/Modules/Admin/views');
    }

    public function testTheModuleClassHasNoSuffix(): void
    {
        // NamedArtifactCommand asks each command for the suffix of its class.
        // The class of a module is always Module, so make:module gives none.
        $suffix = new ReflectionMethod(ModuleCommand::class, 'suffix');

        $this->assertSame('', $suffix->invoke(new ModuleCommand()));
    }

    public function testTheNamespacesKeyGivesTheNamespace(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc', 'namespaces' => ['module' => 'Acme\\\\Mods']]");

        $this->runCommand(['admin']);

        $module = (string) file_get_contents($this->root . self::MODULE);

        $this->assertStringContainsString("namespace Acme\\Mods\\Admin;\n", $module);
        $this->assertStringContainsString("setDefaultNamespace('Acme\\Mods\\Admin\\Controllers');", $module);
    }

    public function testTheWholeModuleIsRendered(): void
    {
        // Asserted whole: this is generated code that nobody reviews.
        $this->runCommand(['admin']);

        $this->assertSame($this->wholeModule(), (string) file_get_contents($this->root . self::MODULE));
    }

    public function testTwoFoldersWithOneKeyStop(): void
    {
        mkdir($this->root . '/src/Modules/Admin', 0o775, true);
        mkdir($this->root . '/src/Modules/ADMIN', 0o775, true);

        $status = $this->runCommand(['admin']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("two folders give the module 'admin'", $this->readStderr());
        $this->assertFileDoesNotExist($this->root . self::MODULE);
        $this->assertFileDoesNotExist($this->root . '/src/Modules/ADMIN/Module.php');
    }

    /**
     * Empty streams for the output of the next run.
     */
    private function freshStreams(): void
    {
        $this->closeStreams();
        $this->captureStreams();
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('make:module', ModuleCommand::class, $arguments);
    }

    private function wholeModule(): string
    {
        return "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\\Modules\\Admin;\n"
            . "\n"
            . "use Phalcon\\Di\\DiInterface;\n"
            . "use Phalcon\\Mvc\\Dispatcher;\n"
            . "use Phalcon\\Mvc\\ModuleDefinitionInterface;\n"
            . "use Phalcon\\Mvc\\View;\n"
            . "\n"
            . "/**\n"
            . " * The admin module. The application calls registerServices() when a\n"
            . " * route sends the request to this module.\n"
            . " *\n"
            . " * Add the services of this module in registerServices(). Composer loads the\n"
            . " * classes of the module, so registerAutoloaders() is empty.\n"
            . " */\n"
            . "final class Module implements ModuleDefinitionInterface\n"
            . "{\n"
            . "    public function registerAutoloaders(?DiInterface \$container = null): void\n"
            . "    {\n"
            . "    }\n"
            . "\n"
            . "    public function registerServices(DiInterface \$container): void\n"
            . "    {\n"
            . "        // The providers of the project set the dispatcher and the view. The\n"
            . "        // module changes only where they look.\n"
            . "        /** @var Dispatcher \$dispatcher */\n"
            . "        \$dispatcher = \$container->getShared('dispatcher');\n"
            . "        \$dispatcher->setDefaultNamespace('App\\Modules\\Admin\\Controllers');\n"
            . "\n"
            . "        /** @var View \$view */\n"
            . "        \$view = \$container->getShared('view');\n"
            . "        \$view->setViewsDir(__DIR__ . '/views/');\n"
            . "    }\n"
            . "}\n";
    }
}
