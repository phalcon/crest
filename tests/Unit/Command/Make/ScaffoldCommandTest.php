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

use Crest\Command\Make\ScaffoldCommand;
use Crest\Tests\Support\GeneratesInAScratchProject;
use Crest\Tests\Support\Project\FailingFront;
use PHPUnit\Framework\TestCase;

use const PHP_EOL;

/**
 * make:scaffold stops before it boots the project (spec 3.4). FailingFront
 * fails to boot, so the error of each check is on stderr only when the check
 * comes before the boot. The tests that read a table are in tests/Database.
 */
final class ScaffoldCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('make-scaffold');
        $this->front();
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function paths(): iterable
    {
        yield 'form' => ['form'];
        yield 'controller' => ['controller'];
        yield 'model' => ['model'];
    }

    public function testAClassWithALowercaseLetterStopsBeforeTheBoot(): void
    {
        $this->front(", 'models' => ['classes' => ['users' => 'user']]");

        $this->assertScaffoldStops(
            ['users'],
            "'users' gives userController, which the dispatcher cannot find from a URL; use a name like 'Users'"
        );
    }

    public function testAnAdrProjectStops(): void
    {
        $this->writeCrestPhp("['flavor' => 'adr']");

        $this->assertScaffoldStops(
            ['users'],
            "make:scaffold does not support the 'adr' flavor of this project; it supports: mvc"
        );
    }

    public function testAnUnknownModuleStopsBeforeTheBoot(): void
    {
        $this->assertScaffoldStops(
            ['users', '--module=admin'],
            "module 'admin' was not found: no Module.php in " . $this->root
            . '/src/Modules/Admin; run crest make:module admin first'
        );
    }

    /**
     * @dataProvider paths
     */
    public function testAPathOutsidePsr4StopsBeforeTheBoot(string $key): void
    {
        $this->front(", 'paths' => ['" . $key . "' => 'lib/Things']");

        $this->assertScaffoldStops(['users'], "no psr-4 autoload entry covers 'lib/Things'");
    }

    public function testATableThatEndsInControllerStopsBeforeTheBoot(): void
    {
        $this->assertScaffoldStops(
            ['users_controller'],
            "'users_controller' gives UsersControllerController, with 'Controller' twice; "
            . "type the name without 'Controller'"
        );
    }

    public function testATableThatGivesNoClassStopsBeforeTheBoot(): void
    {
        $this->assertScaffoldStops(
            ['9 lives'],
            "'9 lives' gives no usable class name; expected a table name like 'users' or 'user_profiles'"
        );
    }

    public function testTheHelp(): void
    {
        $definition = (new ScaffoldCommand())->define();
        $arguments  = $definition->getArguments();
        $force      = $definition->findOption('force');
        $module     = $definition->findOption('module');

        $this->assertSame('make:scaffold', $definition->getName());
        $this->assertSame('Create MVC CRUD pages for a database table', $definition->getDescription());
        $this->assertCount(1, $arguments);
        $this->assertSame('table', $arguments[0]->name);
        $this->assertTrue($arguments[0]->required);
        $this->assertSame('Table name, e.g. users', $arguments[0]->description);
        $this->assertNotNull($force);
        $this->assertSame('Overwrite the controller, the form and the views', $force->description);
        $this->assertNotNull($module);
        $this->assertSame('Write the pages into a module, e.g. admin', $module->description);
    }

    public function testTheTableIsRequired(): void
    {
        $this->assertSame(1, $this->runProjectCommand('make:scaffold', ScaffoldCommand::class, []));
        $this->assertStringContainsString('table', $this->readStderr());
    }

    /**
     * The command stops with the error on stderr, and makes no folder.
     *
     * @param list<string> $arguments
     */
    private function assertScaffoldStops(array $arguments, string $error): void
    {
        $this->assertSame(1, $this->runProjectCommand('make:scaffold', ScaffoldCommand::class, $arguments));
        $this->assertSame('crest: ' . $error . PHP_EOL, $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/src/Forms');
        $this->assertDirectoryDoesNotExist($this->root . '/src/Models');
        $this->assertDirectoryDoesNotExist($this->root . '/src/Controllers');
    }

    /**
     * An MVC crest.php with a front that fails to boot, and the other keys of
     * the test.
     */
    private function front(string $keys = ''): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => \\" . FailingFront::class . '::class' . $keys . ']');
    }
}
