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

namespace Crest\Tests\Database\Command\Make;

use Crest\Command\InitCommand;
use Crest\Command\Make\DatabaseCommand;
use Crest\Tests\Database\Command\AbstractNewCommandTestCase;
use Crest\Tests\Support\Migrations\CustomersMigration;
use PDO;

use function file_get_contents;
use function file_put_contents;
use function preg_match;
use function str_replace;
use function unlink;

/**
 * make:database and `crest init --database` in a project that exists (#95,
 * #96): a project from `crest new` with no database, and the driver of the
 * suite. After the printed line in the front controller, the db service of
 * the project opens its SQLite file, or connects to the database of the
 * suite with the DB_* values that docker compose gives to the app container.
 *
 * The generated classes stay loaded in the PHP process, so each test has a
 * namespace of its own.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class DatabaseCommandTest extends AbstractNewCommandTestCase
{
    public function testAnAdrProjectGetsADbService(): void
    {
        $project = $this->generate('adr', 'none', 'Generated\\MakeDatabase\\Adr');

        $this->makeDatabase($project);

        // make:database loads the front to look for registerProviders(), so
        // the process cannot load a changed AppFront. The printed method goes
        // into a copy of the front with another name.
        $front = (string) file_get_contents($project . '/src/AppFront.php');

        file_put_contents(
            $project . '/src/DatabaseFront.php',
            str_replace(
                ['final class AppFront', "\n}\n"],
                ['final class DatabaseFront', "\n" . $this->printed('/^    protected function .*?^    }\n/ms') . "}\n"],
                $front
            )
        );
        $this->replace($project . '/crest.php', 'AppFront::class', 'DatabaseFront::class');
        $this->createCustomers($project);

        $this->assertEquals(0, $this->adrDatabase($project)->fetchValue('SELECT COUNT(*) FROM customers'));
    }

    public function testAnMvcProjectGetsADbService(): void
    {
        $project = $this->generate('mvc', 'none', 'Generated\\MakeDatabase\\Mvc');

        $this->makeDatabase($project);
        $this->addProvider($project);
        $this->createCustomers($project);

        $this->assertTrue($this->mvcDatabase($project)->tableExists('customers'));
        // The last line of the output: make:model reads the database.
        $this->makeModel($project, 'customers', 'CustomersBase');
    }

    public function testInitAddsTheDatabaseToAProjectWithNoCrestPhp(): void
    {
        $project = $this->generate('mvc', 'none', 'Generated\\MakeDatabase\\Init');

        unlink($project . '/crest.php');

        $this->assertSame(
            0,
            $this->runThroughKernel(
                'init',
                InitCommand::class,
                ['--database', $this->getDriver(), '--directory', $project]
            ),
            $this->readStderr()
        );

        $this->addProvider($project);
        $this->createCustomers($project);

        $this->assertTrue($this->mvcDatabase($project)->tableExists('customers'));
    }

    /**
     * Adds the printed line to providers() of the MVC front, before the
     * provider of the dispatcher.
     */
    private function addProvider(string $project): void
    {
        $this->replace(
            $project . '/src/AppFront.php',
            "            new DispatcherProvider(),\n",
            '            ' . $this->printed('/^    (new \\\\.*DbProvider\(.*\),)$/m', 1) . "\n"
            . "            new DispatcherProvider(),\n"
        );
    }

    /**
     * The table customers: in the SQLite file that make:database writes, or
     * in the database of the suite, with its values in the environment.
     */
    private function createCustomers(string $project): void
    {
        if ('sqlite' === $this->getDriver()) {
            (new CustomersMigration(new PDO('sqlite:' . $project . '/storage/database.sqlite'), false))->create();

            return;
        }

        $this->databaseValues(true, false);
        $this->createTables(CustomersMigration::class);
    }

    private function makeDatabase(string $project): void
    {
        $this->assertSame(
            0,
            $this->runThroughKernel(
                'make:database',
                DatabaseCommand::class,
                [$this->getDriver(), '--directory', $project]
            ),
            $this->readStderr()
        );
    }

    /**
     * A part of the output of the command, as the developer copies it.
     */
    private function printed(string $pattern, int $group = 0): string
    {
        if (1 !== preg_match($pattern, $this->readStdout(), $matches)) {
            $this->fail('the output has no ' . $pattern);
        }

        return $matches[$group];
    }

    private function replace(string $file, string $search, string $replace): void
    {
        $contents = (string) file_get_contents($file);

        $this->assertStringContainsString($search, $contents);

        file_put_contents($file, str_replace($search, $replace, $contents));
    }
}
