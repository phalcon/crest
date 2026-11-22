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

use Crest\Command\Make\DatabaseCommand;
use Crest\Tests\Support\GeneratesInAScratchProject;
use Crest\Tests\Support\Project\ProvidersFront;
use PHPUnit\Framework\TestCase;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function str_replace;

use const PHP_EOL;

/**
 * make:database in a scratch project (#95). The tests that boot a project
 * with the database are in tests/Database.
 */
final class DatabaseCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('make-database');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testACliProjectStops(): void
    {
        $this->writeCrestPhp("['flavor' => 'cli']");

        $this->assertDatabaseStops(
            ['sqlite'],
            "make:database does not support the 'cli' flavor of this project; it supports: adr, mvc"
        );
    }

    public function testADeclaredProviderPathGivesTheFileAndTheNamespace(): void
    {
        $this->writeComposerJson(['Shop\\' => 'app/']);
        $this->writeCrestPhp("['flavor' => 'mvc', 'paths' => ['provider' => 'app/Services']]");

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertStringContainsString(
            "namespace Shop\\Services;\n",
            (string) file_get_contents($this->root . '/app/Services/DbProvider.php')
        );
        $this->assertStringContainsString(
            '    new \Shop\Services\DbProvider($this->projectRoot),' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAFrontWithItsOwnOverrideGetsOnlyTheProvideLine(): void
    {
        // #81: a second registerProviders() is a fatal error.
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . ProvidersFront::class . '::class]');

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertStringContainsString(
            'Nothing registers it yet. Add this line to registerProviders() in' . PHP_EOL
            . ProvidersFront::class . ', after the parent call:' . PHP_EOL
            . PHP_EOL
            . '        (new \App\Provider\DbProvider($this->projectRoot))->provide($container);' . PHP_EOL
            . PHP_EOL
            . 'Add to .gitignore:',
            $this->readStdout()
        );
    }

    public function testAnExistingProviderStopsAndWritesNothing(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");
        mkdir($this->root . '/src/Providers', 0o775, true);
        file_put_contents($this->root . '/src/Providers/DbProvider.php', 'mine');

        $this->assertDatabaseStops(
            ['sqlite'],
            $this->root . '/src/Providers/DbProvider.php already exists; pass --force to overwrite'
        );
        $this->assertSame('mine', (string) file_get_contents($this->root . '/src/Providers/DbProvider.php'));
    }

    public function testAnUnknownDriverStops(): void
    {
        $this->assertDatabaseStops(
            ['oracle'],
            "unknown database 'oracle'; expected none, mariadb, mysql, pgsql or sqlite"
        );
    }

    public function testAServerDatabaseGetsItsProviderAndItsEnvLines(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        // The name of the database comes from the folder of the project.
        $name = str_replace('-', '_', basename($this->root));

        $this->assertSame(0, $this->runCommand(['mysql']));

        $provider = (string) file_get_contents($this->root . '/src/Providers/DbProvider.php');
        $stdout   = $this->readStdout();

        $this->assertStringContainsString("use Phalcon\\Db\\Adapter\\Pdo\\Mysql;\n", $provider);
        $this->assertStringContainsString("'dbname'   => self::env('DB_NAME', '" . $name . "'),\n", $provider);
        $this->assertStringContainsString('    new \App\Providers\DbProvider(),' . PHP_EOL, $stdout);
        $this->assertStringContainsString(
            PHP_EOL . 'Add to .env:' . PHP_EOL . PHP_EOL,
            $stdout
        );
        $this->assertStringContainsString('    DB_HOST=database' . PHP_EOL, $stdout);
        $this->assertStringContainsString('    DB_NAME=' . $name . PHP_EOL, $stdout);
        $this->assertStringNotContainsString('.gitignore', $stdout);
        $this->assertStringEndsWith(PHP_EOL . PHP_EOL . 'Then crest make:model <table> reads it.' . PHP_EOL, $stdout);
        $this->assertFileDoesNotExist($this->root . '/storage/database.sqlite');
    }

    public function testForceWritesTheProviderAgainAndKeepsTheDatabase(): void
    {
        // git ignores the SQLite file, so nothing can restore its data.
        $this->writeCrestPhp("['flavor' => 'mvc']");
        mkdir($this->root . '/src/Providers', 0o775, true);
        mkdir($this->root . '/storage', 0o775, true);
        file_put_contents($this->root . '/src/Providers/DbProvider.php', 'stale');
        file_put_contents($this->root . '/storage/database.sqlite', 'data');

        $this->assertSame(0, $this->runCommand(['sqlite', '--force']));
        $this->assertStringContainsString(
            'final class DbProvider',
            (string) file_get_contents($this->root . '/src/Providers/DbProvider.php')
        );
        $this->assertSame('data', (string) file_get_contents($this->root . '/storage/database.sqlite'));
        $this->assertStringContainsString(
            'Kept ' . $this->root . '/storage/database.sqlite: it exists' . PHP_EOL
            . 'Created ' . $this->root . '/storage/.gitkeep' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testNoneStops(): void
    {
        $this->assertDatabaseStops(['none'], 'no database to add; choose mariadb, mysql, pgsql or sqlite');
    }

    public function testSqliteInAnAdrProject(): void
    {
        $this->writeCrestPhp("['flavor' => 'adr']");

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertSame(
            'Created ' . $this->root . '/src/Provider/DbProvider.php' . PHP_EOL
            . 'Created ' . $this->root . '/storage/database.sqlite' . PHP_EOL
            . 'Created ' . $this->root . '/storage/.gitkeep' . PHP_EOL
            . 'Nothing registers it yet. Call it from your front controller:' . PHP_EOL
            . PHP_EOL
            . '    protected function registerProviders(Container $container): void' . PHP_EOL
            . '    {' . PHP_EOL
            . '        parent::registerProviders($container);' . PHP_EOL
            . PHP_EOL
            . '        (new \App\Provider\DbProvider($this->projectRoot))->provide($container);' . PHP_EOL
            . '    }' . PHP_EOL
            . PHP_EOL
            . 'Keep the parent call: it is what registers the ADR services.' . PHP_EOL
            . PHP_EOL
            . 'Add to .gitignore:' . PHP_EOL
            . PHP_EOL
            . '    /storage/database.sqlite' . PHP_EOL
            . PHP_EOL
            . 'Add to .env:' . PHP_EOL
            . PHP_EOL
            . '    # The database: a SQLite file, relative to the project root. PHP on the host' . PHP_EOL
            . '    # does not read this file: there, the default in DbProvider applies.' . PHP_EOL
            . '    DB_NAME=storage/database.sqlite' . PHP_EOL
            . PHP_EOL
            . 'Then crest make:repository <table> reads it.' . PHP_EOL,
            $this->readStdout()
        );

        $provider = (string) file_get_contents($this->root . '/src/Provider/DbProvider.php');

        $this->assertStringContainsString("namespace App\\Provider;\n", $provider);
        $this->assertStringContainsString("\$services->setAlias(Connection::class, 'db');\n", $provider);
    }

    public function testSqliteInAnMvcProject(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertSame(
            'Created ' . $this->root . '/src/Providers/DbProvider.php' . PHP_EOL
            . 'Created ' . $this->root . '/storage/database.sqlite' . PHP_EOL
            . 'Created ' . $this->root . '/storage/.gitkeep' . PHP_EOL
            . 'Nothing registers it yet. Add it to providers() in your front controller:' . PHP_EOL
            . PHP_EOL
            . '    new \App\Providers\DbProvider($this->projectRoot),' . PHP_EOL
            . PHP_EOL
            . 'In a project with another front controller, register it with' . PHP_EOL
            . '$di->register(new \App\Providers\DbProvider($this->projectRoot)).' . PHP_EOL
            . PHP_EOL
            . 'Add to .gitignore:' . PHP_EOL
            . PHP_EOL
            . '    /storage/database.sqlite' . PHP_EOL
            . PHP_EOL
            . 'Add to .env:' . PHP_EOL
            . PHP_EOL
            . '    # The database: a SQLite file, relative to the project root. PHP on the host' . PHP_EOL
            . '    # does not read this file: there, the default in DbProvider applies.' . PHP_EOL
            . '    DB_NAME=storage/database.sqlite' . PHP_EOL
            . PHP_EOL
            . 'Then crest make:model <table> reads it.' . PHP_EOL,
            $this->readStdout()
        );

        $provider = (string) file_get_contents($this->root . '/src/Providers/DbProvider.php');

        $this->assertStringContainsString("namespace App\\Providers;\n", $provider);
        $this->assertStringContainsString("use Phalcon\\Db\\Adapter\\Pdo\\Sqlite;\n", $provider);
        $this->assertSame('', (string) file_get_contents($this->root . '/storage/database.sqlite'));
        $this->assertSame('', (string) file_get_contents($this->root . '/storage/.gitkeep'));
    }

    public function testTheDriverIsRequired(): void
    {
        $this->assertSame(1, $this->runCommand([]));
        $this->assertStringContainsString('driver', $this->readStderr());
    }

    public function testTheHelp(): void
    {
        $definition = (new DatabaseCommand())->define();
        $arguments  = $definition->getArguments();
        $force      = $definition->findOption('force');

        $this->assertSame('make:database', $definition->getName());
        $this->assertSame('Add a database to the project', $definition->getDescription());
        $this->assertCount(1, $arguments);
        $this->assertSame('driver', $arguments[0]->name);
        $this->assertTrue($arguments[0]->required);
        $this->assertSame('The database: mariadb, mysql, pgsql or sqlite', $arguments[0]->description);
        $this->assertNotNull($force);
        $this->assertSame('Overwrite an existing DbProvider', $force->description);
    }

    /**
     * The command stops with the error on stderr, and writes no SQLite file.
     *
     * @param list<string> $arguments
     */
    private function assertDatabaseStops(array $arguments, string $error): void
    {
        $this->assertSame(1, $this->runCommand($arguments));
        $this->assertSame('crest: ' . $error . PHP_EOL, $this->readStderr());
        $this->assertFileDoesNotExist($this->root . '/storage/database.sqlite');
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('make:database', DatabaseCommand::class, $arguments);
    }
}
