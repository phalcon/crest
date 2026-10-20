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

namespace Crest\Tests\Database\Command;

use Closure;
use Crest\Command\Make\ModelCommand;
use Crest\Command\NewCommand;
use Crest\Project\Bootstrap;
use Crest\Project\Config;
use Crest\Project\Database;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\GeneratesInAScratchProject;
use Phalcon\Container\Container;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Di\Di;

use function is_file;
use function putenv;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The base of the database tests of `crest new --database` (#54, spec 4.2).
 * A test writes a project with a database, loads its classes, and connects
 * through its DbProvider: for MVC as make:model does, for ADR through the
 * container of the front. The classes stay loaded in the PHP process, so
 * each test class gives each flavor a namespace that no other test uses.
 */
abstract class AbstractNewCommandTestCase extends AbstractDatabaseTestCase
{
    use GeneratesInAScratchProject;

    /** @var list<Closure> */
    private array $autoloaders = [];

    /** @var list<string> The DB_* names that the test set. */
    private array $names = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('new-database');
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        foreach ($this->names as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }

        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    /**
     * The db service of an ADR project, from the container of its front: the
     * front registers DbProvider in registerProviders().
     */
    protected function adrDatabase(string $project): AdapterInterface
    {
        $container = Bootstrap::container(Config::discover($project));

        if (false === $container instanceof Container) {
            $this->fail('the ADR front did not give a Phalcon container');
        }

        $adapter = $container->get('db');

        if (false === $adapter instanceof AdapterInterface) {
            $this->fail('the db service is not a Phalcon database adapter');
        }

        // The container keeps the adapter: one connection for a request.
        $this->assertSame($adapter, $container->get('db'));

        return $adapter;
    }

    /**
     * Sets a value of the environment of the project: in the real environment
     * (getenv()), in $_ENV, or in both. tearDown() removes it.
     */
    protected function environment(string $name, string $value, bool $real = true, bool $env = false): void
    {
        if (true === $real) {
            putenv($name . '=' . $value);
        }

        if (true === $env) {
            $_ENV[$name] = $value;
        }

        $this->names[] = $name;
    }

    /**
     * Runs `new` with the database of the test, and loads the classes of the
     * project as its composer.json does: the root namespace maps to src/. The
     * project has no vendor/ yet.
     */
    protected function generate(string $flavor, string $database, string $namespace): string
    {
        $status = $this->runProjectCommand(
            'new',
            NewCommand::class,
            ['shop', '--flavor', $flavor, '--database', $database, '--namespace', $namespace]
        );

        $this->assertSame(0, $status, $this->readStderr());

        $project    = $this->root . '/shop';
        $source     = $project . '/src/';
        $autoloader = static function (string $class) use ($namespace, $source): void {
            $prefix = $namespace . '\\';

            if (false === str_starts_with($class, $prefix)) {
                return;
            }

            $file = $source . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (true === is_file($file)) {
                require $file;
            }
        };

        spl_autoload_register($autoloader);

        $this->autoloaders[] = $autoloader;

        return $project;
    }

    /**
     * Runs make:model for a table in the project, as `./crest make:model`
     * does there, and checks that it wrote the base.
     */
    protected function makeModel(string $project, string $table, string $base): void
    {
        $status = $this->runThroughKernel('make:model', ModelCommand::class, [$table, '--directory', $project]);

        $this->assertSame(0, $status, $this->readStderr());
        $this->assertFileExists($project . '/src/Models/Base/' . $base . '.php');
    }

    /**
     * The db service of an MVC project, as make:model gets it.
     */
    protected function mvcDatabase(string $project): AdapterInterface
    {
        return Database::adapter(Config::discover($project));
    }
}
