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
use Crest\Tests\Support\Project\EmptyFront;
use Crest\Tests\Support\Project\FailingDatabaseFront;
use Crest\Tests\Support\Project\FailingFront;
use Crest\Tests\Support\Project\NotAConnectionFront;
use Crest\Tests\Support\Project\ProvidersFront;
use Crest\Tests\Support\Project\WrongContainerFront;
use Phalcon\Di\Di;
use PHPUnit\Framework\TestCase;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function str_replace;
use function substr;

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
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
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

    public function testAComposeFileWithADatabaseServiceGetsANote(): void
    {
        // #96: a text check of the services; compose.yaml is a name that
        // docker compose looks for.
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => 'App\\AppFront']");
        file_put_contents(
            $this->root . '/compose.yaml',
            "services:\n  app:\n    build: .\n  database:\n    image: mariadb\n"
        );

        $this->assertSame(0, $this->runCommand(['mariadb']));

        $stdout = $this->readStdout();

        $this->assertStringContainsString(
            '$di->register(new \App\Providers\DbProvider()).' . PHP_EOL
            . PHP_EOL
            . "compose.yaml has a service 'database' already, so crest prints no service. "
            . 'DbProvider connects to it.' . PHP_EOL
            . PHP_EOL
            . 'Add to the app service of compose.yaml:' . PHP_EOL,
            $stdout
        );
        $this->assertStringNotContainsString('Add the service', $stdout);
        $this->assertStringContainsString('    docker-php-ext-install pdo_mysql' . PHP_EOL, $stdout);
    }

    public function testADeclaredProviderPathGivesTheFileAndTheNamespace(): void
    {
        $this->writeComposerJson(['Shop\\' => 'app/']);
        $this->writeCrestPhp(
            "['flavor' => 'mvc', 'bootstrap' => 'App\\AppFront', 'paths' => ['provider' => 'app/Services']]"
        );

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

    public function testAFrontThatDoesNotBootGoesOn(): void
    {
        // #97: for example a project with no vendor/. The file check stays.
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . FailingFront::class . '::class]');

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertFileExists($this->root . '/src/Provider/DbProvider.php');
    }

    public function testAFrontWithADbServiceStops(): void
    {
        // #97: the DI of the front has a db service, and the provider folder
        // has no DbProvider. The DI does not connect for has().
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => \\" . FailingDatabaseFront::class . '::class]');

        $this->assertDatabaseStops(
            ['sqlite'],
            "the project container has a 'db' service already; make:database adds no DbProvider"
        );
        $this->assertFileDoesNotExist($this->root . '/src/Providers/DbProvider.php');
    }

    public function testAFrontWithAnotherContainerGoesOn(): void
    {
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . WrongContainerFront::class . '::class]');

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertFileExists($this->root . '/src/Provider/DbProvider.php');
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

    public function testAFrontWithNoDbServiceGoesOn(): void
    {
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . EmptyFront::class . '::class]');

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertFileExists($this->root . '/src/Provider/DbProvider.php');
    }

    public function testAnAdrFrontWithADbServiceStops(): void
    {
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . NotAConnectionFront::class . '::class]');

        $this->assertDatabaseStops(
            ['sqlite'],
            "the project container has a 'db' service already; make:database adds no DbProvider"
        );
        $this->assertFileDoesNotExist($this->root . '/src/Provider/DbProvider.php');
    }

    public function testAnAppServiceNamedDatabaseStops(): void
    {
        // Compose would have one service for two, as in `crest new`.
        $this->writeCrestPhp("['flavor' => 'mvc', 'runtime' => ['type' => 'docker', 'service' => 'database']]");

        $this->assertDatabaseStops(
            ['mysql'],
            "the service 'database' is the name of the database service; choose another service name"
        );
        $this->assertFileDoesNotExist($this->root . '/src/Providers/DbProvider.php');
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

    public function testAServerDatabaseGetsTheLinesOfTheComposeFileTheDockerfileAndEnv(): void
    {
        // #96: the text of each part comes from the stubs that `new` uses.
        // The names come from the folder of the project.
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => 'App\\AppFront']");
        file_put_contents($this->root . '/docker-compose.yml', "services:\n  app:\n    build: .\n");

        $project = basename($this->root);
        $name    = str_replace('-', '_', $project);

        $this->assertSame(0, $this->runCommand(['pgsql']));
        $this->assertSame(
            'Created ' . $this->root . '/src/Providers/DbProvider.php' . PHP_EOL
            . 'Nothing registers it yet. Add it to providers() in your front controller:' . PHP_EOL
            . PHP_EOL
            . '    new \App\Providers\DbProvider(),' . PHP_EOL
            . PHP_EOL
            . 'In a project with another front controller, register it with' . PHP_EOL
            . '$di->register(new \App\Providers\DbProvider()).' . PHP_EOL
            . PHP_EOL
            . 'Add the service and its volume to docker-compose.yml:' . PHP_EOL
            . PHP_EOL
            . '      database:' . PHP_EOL
            . '        image: postgres:18' . PHP_EOL
            . '        container_name: ${PROJECT_PREFIX:-' . $project . '}-database' . PHP_EOL
            . '        environment:' . PHP_EOL
            . '          POSTGRES_DB: ${DB_NAME}' . PHP_EOL
            . '          POSTGRES_USER: ${DB_USERNAME}' . PHP_EOL
            . '          POSTGRES_PASSWORD: ${DB_PASSWORD}' . PHP_EOL
            . '        ports:' . PHP_EOL
            . '          - "127.0.0.1:${DB_PUBLISHED_PORT:-5432}:5432"' . PHP_EOL
            . '        volumes:' . PHP_EOL
            . '          - database:/var/lib/postgresql' . PHP_EOL
            . '        healthcheck:' . PHP_EOL
            . '          test: ["CMD-SHELL", "pg_isready -h 127.0.0.1 -U \"$${POSTGRES_USER}\" -d \"$${POSTGRES_DB}\""]'
            . PHP_EOL
            . '          interval: 5s' . PHP_EOL
            . '          timeout: 5s' . PHP_EOL
            . '          retries: 30' . PHP_EOL
            . PHP_EOL
            . '    volumes:' . PHP_EOL
            . '      database:' . PHP_EOL
            . PHP_EOL
            . 'Add to the app service of docker-compose.yml:' . PHP_EOL
            . PHP_EOL
            . '        depends_on:' . PHP_EOL
            . '          database:' . PHP_EOL
            . '            condition: service_healthy' . PHP_EOL
            . PHP_EOL
            . 'Add to the Dockerfile of the app service, after apt-get update:' . PHP_EOL
            . PHP_EOL
            . '    apt-get install -yq --no-install-recommends libpq-dev' . PHP_EOL
            . '    docker-php-ext-install pdo_pgsql' . PHP_EOL
            . PHP_EOL
            . 'Add to .env:' . PHP_EOL
            . PHP_EOL
            . '    # The database. docker compose creates it from these values, and gives them to the' . PHP_EOL
            . '    # app container. PHP on the host does not read this file: there, the defaults in' . PHP_EOL
            . '    # DbProvider apply.' . PHP_EOL
            . '    DB_HOST=database' . PHP_EOL
            . '    DB_PORT=5432' . PHP_EOL
            . '    DB_NAME=' . $name . PHP_EOL
            . '    DB_USERNAME=' . substr($name, 0, 32) . PHP_EOL
            . '    DB_PASSWORD=secret' . PHP_EOL
            . '    # The port of the database on the host, for PHP on the host or a database client.' . PHP_EOL
            . '    DB_PUBLISHED_PORT=5432' . PHP_EOL
            . PHP_EOL
            . 'Then crest make:model <table> reads it.' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringContainsString(
            "use Phalcon\\Db\\Adapter\\Pdo\\Postgresql;\n",
            (string) file_get_contents($this->root . '/src/Providers/DbProvider.php')
        );
        $this->assertFileDoesNotExist($this->root . '/storage/database.sqlite');
    }

    public function testAServerDatabaseWithNoComposeFileGetsANote(): void
    {
        // Without a compose file, nothing gives .env to the app: crest prints
        // no .env lines, and the defaults of DbProvider apply.
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => 'App\\AppFront']");

        $name = str_replace('-', '_', basename($this->root));

        $this->assertSame(0, $this->runCommand(['mysql']));

        $provider = (string) file_get_contents($this->root . '/src/Providers/DbProvider.php');

        $this->assertStringContainsString("use Phalcon\\Db\\Adapter\\Pdo\\Mysql;\n", $provider);
        $this->assertStringContainsString("'dbname'   => self::env('DB_NAME', '" . $name . "'),\n", $provider);
        $this->assertStringEndsWith(
            '$di->register(new \App\Providers\DbProvider()).' . PHP_EOL
            . PHP_EOL
            . 'The project has no compose file. PHP needs pdo_mysql, and the defaults of DbProvider connect to '
            . '127.0.0.1, port 3306.' . PHP_EOL
            . PHP_EOL
            . 'Then crest make:model <table> reads it.' . PHP_EOL,
            $this->readStdout()
        );
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

    public function testForceWritesTheProviderAgainWithADbService(): void
    {
        // #97: DbProvider gives the db service itself, so the file check
        // decides, and --force writes it again.
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => \\" . FailingDatabaseFront::class . '::class]');
        mkdir($this->root . '/src/Providers', 0o775, true);
        file_put_contents($this->root . '/src/Providers/DbProvider.php', 'stale');

        $this->assertSame(0, $this->runCommand(['sqlite', '--force']));
        $this->assertStringContainsString(
            'final class DbProvider',
            (string) file_get_contents($this->root . '/src/Providers/DbProvider.php')
        );
    }

    public function testNoFrontInAnAdrProjectGetsTheProvideLine(): void
    {
        // #97: crest.php has no bootstrap key, so crest prints no front line.
        $this->writeCrestPhp("['flavor' => 'adr']");

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertStringContainsString(
            'Created ' . $this->root . '/storage/.gitkeep' . PHP_EOL
            . 'Nothing registers it yet. crest.php names no front controller, so call it with' . PHP_EOL
            . 'the container of the project:' . PHP_EOL
            . PHP_EOL
            . '    (new \App\Provider\DbProvider($projectRoot))->provide($container);' . PHP_EOL
            . PHP_EOL
            . 'Add to .gitignore:',
            $this->readStdout()
        );
    }

    public function testNoFrontInAnMvcProjectGetsTheLineForTheDi(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->assertSame(0, $this->runCommand(['sqlite']));
        $this->assertStringContainsString(
            'Created ' . $this->root . '/storage/.gitkeep' . PHP_EOL
            . 'Nothing registers it yet. crest.php names no front controller, so register it' . PHP_EOL
            . 'in the DI of the project:' . PHP_EOL
            . PHP_EOL
            . '    $di->register(new \App\Providers\DbProvider($projectRoot));' . PHP_EOL
            . PHP_EOL
            . 'Add to .gitignore:',
            $this->readStdout()
        );
    }

    public function testNoFrontWithAServerDatabaseGivesNoArguments(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->assertSame(0, $this->runCommand(['mysql']));
        $this->assertStringContainsString(
            '    $di->register(new \App\Providers\DbProvider());' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testNoneStops(): void
    {
        $this->assertDatabaseStops(['none'], 'no database to add; choose mariadb, mysql, pgsql or sqlite');
    }

    public function testSqliteInAnAdrProject(): void
    {
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => 'App\\AppFront']");

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
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => 'App\\AppFront']");

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
