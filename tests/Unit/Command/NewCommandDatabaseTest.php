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

namespace Crest\Tests\Unit\Command;

use Crest\Command\NewCommand;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Tests\Support\GeneratesInAScratchProject;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function json_decode;
use function mkdir;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

/**
 * The database of `crest new` (#54): --database and its question, the files
 * of each choice, --camel-case and the models key, and the report. The other
 * tests of new are in NewCommandTest.
 */
final class NewCommandDatabaseTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('new-database');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function databaseProjects(): iterable
    {
        foreach (['adr', 'mvc'] as $flavor) {
            foreach (['sqlite', 'mysql', 'mariadb', 'pgsql'] as $database) {
                foreach (['v5', 'v6'] as $variant) {
                    yield $flavor . ' ' . $database . ' ' . $variant => [$flavor, $database, $variant];
                }
            }
        }
    }

    /**
     * A copy of a project stub that a team published before #54: the stub
     * without one placeholder of #54. Each row: the folder of the copy, the
     * stub, the placeholder, the flavor, and the options that need the
     * placeholder (spec 3.5).
     *
     * @return iterable<string, array{string, string, string, string, list<string>}>
     */
    public static function oldStubs(): iterable
    {
        yield 'compose dependsOn' => ['shared', 'project-compose', 'dependsOn', 'adr', ['--database', 'mysql']];
        yield 'compose databaseService' => [
            'shared',
            'project-compose',
            'databaseService',
            'adr',
            ['--database', 'mariadb'],
        ];
        yield 'dockerfile databaseInstall' => [
            'shared',
            'project-dockerfile',
            'databaseInstall',
            'adr',
            ['--database', 'pgsql'],
        ];
        yield 'env databaseEnv' => ['shared', 'project-env', 'databaseEnv', 'adr', ['--database', 'sqlite']];
        yield 'gitignore databaseIgnore' => [
            'shared',
            'project-gitignore',
            'databaseIgnore',
            'adr',
            ['--database', 'sqlite'],
        ];
        yield 'composer phql' => [
            'shared',
            'project-composer',
            'phql',
            'mvc',
            ['--phalcon', 'v6', '--database', 'mysql'],
        ];
        yield 'mvc front dbUse' => ['mvc', 'project-front', 'dbUse', 'mvc', ['--database', 'mysql']];
        yield 'mvc front dbProvider' => ['mvc', 'project-front', 'dbProvider', 'mvc', ['--database', 'sqlite']];
        yield 'adr front registerProviders' => [
            'adr',
            'project-front',
            'registerProviders',
            'adr',
            ['--database', 'mysql'],
        ];
        yield 'config models' => ['shared', 'project-config', 'models', 'mvc', ['--camel-case']];
    }

    public function testAMariadbProjectGetsTheMariadbService(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'mariadb']));

        $compose = $this->read('docker-compose.yml');

        $this->assertStringContainsString("\n    image: mariadb:11.4\n", $compose);
        $this->assertStringContainsString("\n      MARIADB_DATABASE: \${DB_NAME}\n", $compose);
        $this->assertStringContainsString(
            "\n      test: [\"CMD\", \"healthcheck.sh\", \"--connect\", \"--innodb_initialized\"]\n",
            $compose
        );
        $this->assertStringContainsString(
            "docker-php-ext-install zip\ndocker-php-ext-install pdo_mysql\napt-get clean -y\n",
            $this->read('resources/docker/Dockerfile')
        );
    }

    public function testAMysqlProjectGetsTheDatabaseFiles(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'mysql']));

        $compose = $this->read('docker-compose.yml');

        // The app service waits for the health check of the database.
        $this->assertStringContainsString(
            "    env_file: .env\n"
            . "    depends_on:\n"
            . "      database:\n"
            . "        condition: service_healthy\n"
            . "\n"
            . "  database:\n"
            . "    image: mysql:8.4\n"
            . "    container_name: \${PROJECT_PREFIX:-my-app}-database\n",
            $compose
        );
        $this->assertStringEndsWith("\nvolumes:\n  database:\n", $compose);
        $this->assertSame(
            "# Docker settings. docker-compose.yml reads them.\n"
            . "PROJECT_PREFIX=my-app\n"
            . "APP_PORT=8080\n"
            . "\n"
            . "# Set these to your host user and group IDs if they are not 1000.\n"
            . "# Then the files that the container writes belong to you.\n"
            . "UID=1000\n"
            . "GID=1000\n"
            . "\n"
            . "# The database. docker compose creates it from these values, and gives them to the\n"
            . "# app container. PHP on the host does not read this file: there, the defaults in\n"
            . "# DbProvider apply.\n"
            . "DB_HOST=database\n"
            . "DB_PORT=3306\n"
            . "DB_NAME=my_app\n"
            . "DB_USERNAME=my_app\n"
            . "DB_PASSWORD=secret\n"
            . "# The port of the database on the host, for PHP on the host or a database client.\n"
            . "DB_PUBLISHED_PORT=3306\n",
            $this->read('.env')
        );
        $this->assertStringContainsString(
            "docker-php-ext-install zip\ndocker-php-ext-install pdo_mysql\napt-get clean -y\n",
            $this->read('resources/docker/Dockerfile')
        );
        $this->assertSame("/vendor/\n", $this->read('.gitignore'));
        $this->assertDirectoryDoesNotExist($this->root . '/my-app/storage');
    }

    public function testAnAdrProjectWithADatabaseGetsNoCamelCaseQuestion(): void
    {
        // Flavor, namespace, PHP version, Phalcon version, runtime, service,
        // database. make:model is MVC only.
        $this->answers("adr\n\n\n\n\n\nmysql\n");

        $this->assertSame(0, $this->runCommand(['my-app'], true));
        $this->assertStringEndsWith('Database (none, mariadb, mysql, pgsql, sqlite) [none]: ', $this->readStderr());
        $this->assertStringNotContainsString("'models'", $this->read('crest.php'));
    }

    public function testAnAdrProjectWithADatabaseRegistersItsProvider(): void
    {
        // Asserted whole: this is generated code nobody reviews.
        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'mysql']));

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App;\n"
            . "\n"
            . "use Phalcon\\ADR\\Application;\n"
            . "use Phalcon\\ADR\\Front\\AbstractHttpFront;\n"
            . "use Phalcon\\Container\\Container;\n"
            . "use Phalcon\\Contracts\\ADR\\Application as ApplicationInterface;\n"
            . "\n"
            . "/**\n"
            . " * The front controller. public/index.php runs it for every request, and\n"
            . " * crest boots it for container:list and event:list.\n"
            . " *\n"
            . " * Add services by overriding registerProviders(); `crest make:provider`\n"
            . " * prints the override to paste here.\n"
            . " */\n"
            . "final class AppFront extends AbstractHttpFront\n"
            . "{\n"
            . "    protected function getApplication(Container \$container): ApplicationInterface\n"
            . "    {\n"
            . "        return (new Application(\$container))\n"
            . "            ->setBaseNamespace('App\\Action')\n"
            . "            ->setActionDirectory(\$this->projectRoot . '/src/Action');\n"
            . "    }\n"
            . "\n"
            . "    protected function registerProviders(Container \$container): void\n"
            . "    {\n"
            . "        parent::registerProviders(\$container);\n"
            . "\n"
            . "        (new \\App\\Provider\\DbProvider())->provide(\$container);\n"
            . "    }\n"
            . "}\n",
            $this->read('src/AppFront.php')
        );
        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\\Provider;\n"
            . "\n"
            . "use Phalcon\\Contracts\\Container\\Service\\Collection;\n"
            . "use Phalcon\\Contracts\\Container\\Service\\Provider as ProviderContract;\n"
            . "use Phalcon\\Db\\Adapter\\Pdo\\Mysql;\n"
            . "\n"
            . "/**\n"
            . " * The database. docker compose gives the values of .env to the app container.\n"
            . " * PHP on the host does not read .env: there, the defaults apply, and they point\n"
            . " * to the published port of the database container.\n"
            . " */\n"
            . "final class DbProvider implements ProviderContract\n"
            . "{\n"
            . "    public function provide(Collection \$services): void\n"
            . "    {\n"
            . "        \$config = [\n"
            . "            'host'     => self::env('DB_HOST', '127.0.0.1'),\n"
            . "            'port'     => self::env('DB_PORT', '3306'),\n"
            . "            'dbname'   => self::env('DB_NAME', 'my_app'),\n"
            . "            'username' => self::env('DB_USERNAME', 'my_app'),\n"
            . "            'password' => self::env('DB_PASSWORD', 'secret'),\n"
            . "            'charset'  => 'utf8mb4',\n"
            . "        ];\n"
            . "\n"
            . "        // The container calls the closure at the first get('db'), and keeps\n"
            . "        // the adapter.\n"
            . "        \$services->set('db', static function () use (\$config): Mysql {\n"
            . "            return new Mysql(\$config);\n"
            . "        });\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * The real environment wins (the env_file of docker compose). \$_ENV covers\n"
            . "     * a loader that does not call putenv(), such as phpdotenv.\n"
            . "     */\n"
            . "    private static function env(string \$name, string \$default): string\n"
            . "    {\n"
            . "        \$value = getenv(\$name);\n"
            . "\n"
            . "        if (false !== \$value) {\n"
            . "            return \$value;\n"
            . "        }\n"
            . "\n"
            . "        return (string) (\$_ENV[\$name] ?? \$default);\n"
            . "    }\n"
            . "}\n",
            $this->read('src/Provider/DbProvider.php')
        );
    }

    public function testAnAdrProjectWithSqliteGivesTheProjectRootToItsProvider(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'sqlite']));

        $this->assertStringContainsString(
            "        (new \\App\\Provider\\DbProvider(\$this->projectRoot))->provide(\$container);\n",
            $this->read('src/AppFront.php')
        );

        $provider = $this->read('src/Provider/DbProvider.php');

        $this->assertStringContainsString("use Phalcon\\Db\\Adapter\\Pdo\\Sqlite;\n", $provider);
        $this->assertStringContainsString(
            "    public function __construct(private readonly string \$projectRoot)\n",
            $provider
        );
        $this->assertStringContainsString(
            "            'dbname' => \$this->projectRoot . '/' . self::env('DB_NAME', 'storage/database.sqlite'),\n",
            $provider
        );
    }

    public function testAnAdrV6ProjectWithADatabaseDoesNotRequirePhql(): void
    {
        // make:model is MVC only.
        $this->assertSame(0, $this->runCommand(['my-app', '--phalcon', 'v6', '--database', 'mysql']));
        $this->assertSame(['php' => '>=8.4', 'phalcon/phalcon' => '^6.0@RC'], $this->composer()['require']);
    }

    public function testANameWithCapitalsAndADigitKeepsThemInTheDatabaseNames(): void
    {
        $this->assertSame(0, $this->runCommand(['1My-Shop', '--database', 'pgsql']));
        $this->assertStringContainsString(
            "\nDB_NAME=1My_Shop\nDB_USERNAME=1My_Shop\n",
            (string) file_get_contents($this->root . '/1My-Shop/.env')
        );
    }

    public function testAnMvcProjectWithADatabaseAddsItsProvider(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc', '--database', 'mysql']));

        $front = $this->read('src/AppFront.php');

        $this->assertStringContainsString(
            "use App\\Providers\\DbProvider;\n"
            . "use App\\Providers\\DispatcherProvider;\n"
            . "use App\\Providers\\ViewProvider;\n",
            $front
        );
        $this->assertStringContainsString(
            "        return [\n"
            . "            new DbProvider(),\n"
            . "            new DispatcherProvider(),\n",
            $front
        );
        // Asserted whole: this is generated code nobody reviews.
        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\\Providers;\n"
            . "\n"
            . "use Phalcon\\Db\\Adapter\\Pdo\\Mysql;\n"
            . "use Phalcon\\Di\\DiInterface;\n"
            . "use Phalcon\\Di\\ServiceProviderInterface;\n"
            . "\n"
            . "/**\n"
            . " * The database. docker compose gives the values of .env to the app container.\n"
            . " * PHP on the host does not read .env: there, the defaults apply, and they point\n"
            . " * to the published port of the database container.\n"
            . " */\n"
            . "final class DbProvider implements ServiceProviderInterface\n"
            . "{\n"
            . "    public function register(DiInterface \$di): void\n"
            . "    {\n"
            . "        \$config = [\n"
            . "            'host'     => self::env('DB_HOST', '127.0.0.1'),\n"
            . "            'port'     => self::env('DB_PORT', '3306'),\n"
            . "            'dbname'   => self::env('DB_NAME', 'my_app'),\n"
            . "            'username' => self::env('DB_USERNAME', 'my_app'),\n"
            . "            'password' => self::env('DB_PASSWORD', 'secret'),\n"
            . "            'charset'  => 'utf8mb4',\n"
            . "        ];\n"
            . "\n"
            . "        // Not a static closure: the container binds each closure to itself,\n"
            . "        // and a static closure gives a warning there.\n"
            . "        \$di->setShared('db', function () use (\$config): Mysql {\n"
            . "            return new Mysql(\$config);\n"
            . "        });\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * The real environment wins (the env_file of docker compose). \$_ENV covers\n"
            . "     * a loader that does not call putenv(), such as phpdotenv.\n"
            . "     */\n"
            . "    private static function env(string \$name, string \$default): string\n"
            . "    {\n"
            . "        \$value = getenv(\$name);\n"
            . "\n"
            . "        if (false !== \$value) {\n"
            . "            return \$value;\n"
            . "        }\n"
            . "\n"
            . "        return (string) (\$_ENV[\$name] ?? \$default);\n"
            . "    }\n"
            . "}\n",
            $this->read('src/Providers/DbProvider.php')
        );
    }

    public function testAnMvcProjectWithSqliteGetsEveryFile(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc', '--database', 'sqlite']));

        $prefix = $this->root . '/my-app/';
        $files  = [];

        /** @var SplFileInfo $file */
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($prefix, FilesystemIterator::SKIP_DOTS)
            ) as $file
        ) {
            $files[] = substr($file->getPathname(), strlen($prefix));
        }

        sort($files);

        $this->assertSame(
            [
                '.env',
                '.gitignore',
                '.htrouter.php',
                'README.md',
                'composer.json',
                'crest',
                'crest.php',
                'docker-compose.yml',
                'public/index.php',
                'resources/docker/Dockerfile',
                'src/AppFront.php',
                'src/Controllers/Base/CrestControllerBase.php',
                'src/Controllers/IndexController.php',
                'src/Providers/DbProvider.php',
                'src/Providers/DispatcherProvider.php',
                'src/Providers/ViewProvider.php',
                'storage/.gitkeep',
                'storage/database.sqlite',
                'themes/default/index.phtml',
                'themes/default/index/index.phtml',
            ],
            $files
        );
        $this->assertStringContainsString(
            "            new DbProvider(\$this->projectRoot),\n",
            $this->read('src/AppFront.php')
        );
    }

    public function testAnMvcV5ProjectWithADatabaseDoesNotRequirePhql(): void
    {
        // The extension has PHQL.
        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc', '--database', 'mysql']));
        $this->assertSame(['php' => '>=8.4', 'ext-phalcon' => '^5.18'], $this->composer()['require']);
    }

    public function testAnMvcV6ProjectWithADatabaseRequiresPhql(): void
    {
        $status = $this->runCommand(['my-app', '--flavor', 'mvc', '--phalcon', 'v6', '--database', 'mysql']);

        $this->assertSame(0, $status);
        $this->assertSame(
            ['php' => '>=8.4', 'phalcon/phalcon' => '^6.0@RC', 'phalcon/phql' => '1.0.x-dev'],
            $this->composer()['require']
        );
    }

    /**
     * @param list<string> $options
     *
     * @dataProvider oldStubs
     */
    public function testAnOldPublishedStubStopsANewWithADatabase(
        string $folder,
        string $name,
        string $placeholder,
        string $flavor,
        array $options
    ): void {
        $copy = $this->publishWithout($folder, $name, $placeholder);

        $status = $this->runCommand(['my-app', '--flavor', $flavor, ...$options]);

        $this->assertSame(1, $status);
        $this->assertSame(
            'crest: the published stub ' . $copy . ' has no {{ ' . $placeholder . ' }}; publish it again with '
            . 'crest stub:publish ' . $name . ' --flavor=' . $flavor . ' --force, and make your changes in it again'
            . PHP_EOL,
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    /**
     * @param list<string> $options
     *
     * @dataProvider oldStubs
     */
    public function testAnOldPublishedStubWorksWithoutADatabase(
        string $folder,
        string $name,
        string $placeholder,
        string $flavor,
        array $options
    ): void {
        // Without the options, the value of the placeholder is empty, so
        // nothing is lost: the old copy works as before. $options is not
        // used here; the row of the data provider has it for the other test.
        $this->publishWithout($folder, $name, $placeholder);

        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', $flavor]));
    }

    public function testAnUnknownDatabaseIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--database', 'oracle']);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: unknown database 'oracle'; expected none, mariadb, mysql, pgsql or sqlite" . PHP_EOL,
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAPgsqlProjectInstallsLibpqAndPdoPgsql(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'pgsql']));

        $this->assertStringContainsString(
            "docker-php-ext-install zip\n"
            . "apt-get install -yq --no-install-recommends libpq-dev\n"
            . "docker-php-ext-install pdo_pgsql\n"
            . "apt-get clean -y\n",
            $this->read('resources/docker/Dockerfile')
        );

        $compose = $this->read('docker-compose.yml');

        $this->assertStringContainsString("\n    image: postgres:18\n", $compose);
        $this->assertStringContainsString("\n      - database:/var/lib/postgresql\n", $compose);
        $this->assertStringContainsString("\nDB_PORT=5432\n", $this->read('.env'));
    }

    public function testASqliteProjectGetsItsFileAndNoService(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'sqlite']));

        // An empty file is a valid SQLite database.
        $this->assertSame(0, filesize($this->root . '/my-app/storage/database.sqlite'));
        $this->assertSame('', $this->read('storage/.gitkeep'));
        $this->assertSame("/vendor/\n/storage/database.sqlite\n", $this->read('.gitignore'));
        $this->assertStringEndsWith(
            "GID=1000\n"
            . "\n"
            . "# The database: a SQLite file, relative to the project root. PHP on the host\n"
            . "# does not read this file: there, the default in DbProvider applies.\n"
            . "DB_NAME=storage/database.sqlite\n",
            $this->read('.env')
        );
        // No service, and no extension: the php image has pdo_sqlite.
        $this->assertStringEndsWith("    env_file: .env\n", $this->read('docker-compose.yml'));
        $this->assertStringContainsString(
            "docker-php-ext-install zip\napt-get clean -y\n",
            $this->read('resources/docker/Dockerfile')
        );
    }

    public function testCamelCaseForAnAdrProjectWritesNoModelsKey(): void
    {
        // ADR has no models: the flag has no effect.
        $this->assertSame(0, $this->runCommand(['my-app', '--camel-case']));
        $this->assertStringNotContainsString("'models'", $this->read('crest.php'));
    }

    public function testCamelCaseWithoutADatabaseIsTrue(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc', '--camel-case']));
        $this->assertStringContainsString("        'camelCase' => true,\n", $this->read('crest.php'));
    }

    public function testForceKeepsTheSqliteFileOfTheProject(): void
    {
        // --force overwrites the files with the same names, but not the
        // database file: git ignores it, so nothing can restore its data
        // (Review Focus 1 of the plan).
        $this->runCommand(['my-app', '--database', 'sqlite']);
        file_put_contents($this->root . '/my-app/storage/database.sqlite', 'data');

        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'sqlite', '--force']));
        $this->assertSame('data', $this->read('storage/database.sqlite'));
    }

    public function testForceWritesAMissingSqliteFile(): void
    {
        // The project exists, but it has no database file yet.
        $this->runCommand(['my-app']);

        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'sqlite', '--force']));
        $this->assertFileExists($this->root . '/my-app/storage/database.sqlite');
        $this->assertFileExists($this->root . '/my-app/storage/.gitkeep');
    }

    /**
     * @dataProvider databaseProjects
     */
    public function testNoPlaceholderIsLeftInAProjectWithADatabase(
        string $flavor,
        string $database,
        string $variant
    ): void {
        $this->assertSame(
            0,
            $this->runCommand(['my-app', '--flavor', $flavor, '--database', $database, '--phalcon', $variant])
        );

        $scanned = 0;

        /** @var SplFileInfo $file */
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root . '/my-app', FilesystemIterator::SKIP_DOTS)
            ) as $file
        ) {
            $scanned++;

            $this->assertStringNotContainsString(
                '{{',
                (string) file_get_contents($file->getPathname()),
                sprintf('%s still has a placeholder', $file->getPathname())
            );
        }

        $this->assertGreaterThan(0, $scanned);
    }

    public function testTheCamelCaseQuestionIsOnlyForAnMvcProjectWithADatabase(): void
    {
        // Flavor, namespace, PHP version, Phalcon version, runtime, service,
        // database, camelCase.
        $this->answers("mvc\n\n\n\n\n\nmysql\nyes\n");

        $this->assertSame(0, $this->runCommand(['my-app'], true));
        $this->assertStringEndsWith(
            'Database (none, mariadb, mysql, pgsql, sqlite) [none]: Property names in camelCase (no, yes) [no]: ',
            $this->readStderr()
        );
        $this->assertStringContainsString("        'camelCase' => true,\n", $this->read('crest.php'));
    }

    public function testTheDatabaseIsCaseInsensitive(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--database', 'MySQL']));
        $this->assertStringContainsString("\n    image: mysql:8.4\n", $this->read('docker-compose.yml'));
    }

    public function testTheDatabaseQuestionComesAfterTheService(): void
    {
        $this->answers("adr\n\n\n\n\n\nsqlite\n");

        $this->assertSame(0, $this->runCommand(['my-app'], true));
        $this->assertSame(
            'Flavor (adr, mvc) [adr]: Root namespace [App]: PHP version [8.4]: Phalcon version (v5, v6) [v5]: '
            . 'Runtime (host, docker) [docker]: Docker compose service [app]: '
            . 'Database (none, mariadb, mysql, pgsql, sqlite) [none]: ',
            $this->readStderr()
        );
        $this->assertFileExists($this->root . '/my-app/storage/database.sqlite');
    }

    public function testTheEnvFileIsTheSameForEachRuntime(): void
    {
        // The values do not depend on the runtime: docker compose gives .env
        // to the app container, and PHP on the host uses the defaults of
        // DbProvider (spec 3.2, change 1).
        $this->runCommand(['my-app', '--database', 'mysql', '--runtime', 'host']);

        $env     = $this->read('.env');
        $compose = $this->read('docker-compose.yml');

        $this->assertSame(
            0,
            $this->runCommand(['my-app', '--database', 'mysql', '--runtime', 'docker', '--force'])
        );
        $this->assertSame($env, $this->read('.env'));
        $this->assertSame($compose, $this->read('docker-compose.yml'));
        $this->assertStringContainsString("\nDB_HOST=database\n", $env);
    }

    public function testTheHelpNamesTheNewOptions(): void
    {
        $status = $this->runThroughKernel('new', NewCommand::class, ['--help']);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression(
            '/--database +The database: none, mariadb, mysql, pgsql or sqlite\R/',
            $this->readStdout()
        );
        $this->assertMatchesRegularExpression(
            '/--camel-case +MVC: property names in camelCase \(models\.camelCase of crest\.php\)\R/',
            $this->readStdout()
        );
    }

    public function testTheMvcReportNamesMakeModel(): void
    {
        $this->runCommand(['my-app', '--flavor', 'mvc', '--database', 'pgsql']);

        $this->assertStringEndsWith(
            'Then GET / answers from src/Controllers/IndexController.php' . PHP_EOL
            . PHP_EOL
            . "The database: pgsql, in the docker service 'database'. The values are in .env." . PHP_EOL
            . 'On the host, start it with: docker compose up -d database' . PHP_EOL
            . 'PHP on the host also needs pdo_pgsql.' . PHP_EOL
            . 'Then crest make:model <table> reads it.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheReportNamesTheDatabaseService(): void
    {
        $this->runCommand(['my-app', '--database', 'mysql']);

        $this->assertStringEndsWith(
            'Then GET / answers from src/Action/Get.php' . PHP_EOL
            . PHP_EOL
            . "The database: mysql, in the docker service 'database'. The values are in .env." . PHP_EOL
            . 'On the host, start it with: docker compose up -d database' . PHP_EOL
            . 'PHP on the host also needs pdo_mysql.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheServiceDatabaseIsAcceptedWithSqlite(): void
    {
        // sqlite has no service, so the name is free.
        $this->assertSame(0, $this->runCommand(['my-app', '--service', 'database', '--database', 'sqlite']));
        $this->assertStringStartsWith("services:\n  database:\n", $this->read('docker-compose.yml'));
    }

    public function testTheServiceDatabaseIsRefusedWithAServerDatabase(): void
    {
        $status = $this->runCommand(['my-app', '--service', 'database', '--database', 'mysql']);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: the service 'database' is the name of the database service; choose another service name"
            . PHP_EOL,
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testTheSqliteReportNamesItsFile(): void
    {
        $this->runCommand(['my-app', '--flavor', 'mvc', '--database', 'sqlite']);

        $this->assertStringEndsWith(
            'Then GET / answers from src/Controllers/IndexController.php' . PHP_EOL
            . PHP_EOL
            . 'The database: sqlite, in storage/database.sqlite.' . PHP_EOL
            . 'Then crest make:model <table> reads it.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testWithoutADatabaseAnMvcProjectGetsNoCamelCaseQuestion(): void
    {
        // No tables yet: camelCase false, with no question.
        $this->answers("mvc\n\n\n\n\n\n\n");

        $this->assertSame(0, $this->runCommand(['my-app'], true));
        $this->assertStringEndsWith('Database (none, mariadb, mysql, pgsql, sqlite) [none]: ', $this->readStderr());
        $this->assertStringContainsString("        'camelCase' => false,\n", $this->read('crest.php'));
    }

    public function testWithoutADatabaseTheFilesEndAsBefore(): void
    {
        // Each new placeholder is empty: the files are the same as before #54.
        $this->assertSame(0, $this->runCommand(['my-app']));

        $this->assertStringEndsWith(
            "    volumes:\n      - .:/srv\n    env_file: .env\n",
            $this->read('docker-compose.yml')
        );
        $this->assertStringEndsWith("UID=1000\nGID=1000\n", $this->read('.env'));
        $this->assertSame("/vendor/\n", $this->read('.gitignore'));
        $this->assertStringContainsString(
            "docker-php-ext-install zip\napt-get clean -y\n",
            $this->read('resources/docker/Dockerfile')
        );
        $this->assertFileDoesNotExist($this->root . '/my-app/src/Provider/DbProvider.php');
        $this->assertDirectoryDoesNotExist($this->root . '/my-app/storage');
        // The docblock names registerProviders(); the override is not there.
        $this->assertStringNotContainsString('function registerProviders', $this->read('src/AppFront.php'));
    }

    public function testWithoutInteractionAnMvcProjectWithADatabaseGetsCamelCaseFalse(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc', '--database', 'mysql']));
        $this->assertStringContainsString("        'camelCase' => false,\n", $this->read('crest.php'));
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($this->read('composer.json'), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Publishes the packaged stub without one placeholder, as a copy from
     * before #54, where new reads the copies. Gives the path of the copy.
     */
    private function publishWithout(string $folder, string $name, string $placeholder): string
    {
        $copy = Stub::overridePath($this->root, $folder, $name);

        mkdir(dirname($copy), 0o775, true);
        file_put_contents(
            $copy,
            str_replace(
                '{{ ' . $placeholder . ' }}',
                '',
                (string) file_get_contents(Stub::packagedPath(Paths::stubs(), $folder, $name))
            )
        );

        return $copy;
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root . '/my-app/' . $path);
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments, bool $interactive = false): int
    {
        return $this->runProjectCommand('new', NewCommand::class, $arguments, $interactive);
    }
}
