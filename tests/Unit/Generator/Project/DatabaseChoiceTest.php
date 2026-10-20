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

use Crest\Console\Exceptions\Exception;
use Crest\Generator\Project\DatabaseChoice;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function mkdir;
use function str_repeat;

/**
 * What each choice of `crest new --database` puts in the project (#54, spec
 * 3.1 and 3.2).
 */
final class DatabaseChoiceTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('database-choice');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    /**
     * The server databases: the choice, the image, the port, the extension,
     * the adapter, the charset line, the data folder, the health check and
     * the line that creates the database.
     *
     * @return iterable<string, array{DatabaseChoice, string, string, string, string, string, string, string, string}>
     */
    public static function servers(): iterable
    {
        yield 'mariadb' => [
            DatabaseChoice::MARIADB,
            'mariadb:11.4',
            '3306',
            'pdo_mysql',
            'Mysql',
            "            'charset'  => 'utf8mb4',\n",
            '/var/lib/mysql',
            '      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]',
            '      MARIADB_DATABASE: ${DB_NAME}',
        ];

        yield 'mysql' => [
            DatabaseChoice::MYSQL,
            'mysql:8.4',
            '3306',
            'pdo_mysql',
            'Mysql',
            "            'charset'  => 'utf8mb4',\n",
            '/var/lib/mysql',
            '      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 --silent"]',
            '      MYSQL_DATABASE: ${DB_NAME}',
        ];

        yield 'pgsql' => [
            DatabaseChoice::PGSQL,
            'postgres:18',
            '5432',
            'pdo_pgsql',
            'Postgresql',
            '',
            '/var/lib/postgresql',
            '      test: ["CMD-SHELL", "pg_isready -h 127.0.0.1 -U \"$${POSTGRES_USER}\" -d \"$${POSTGRES_DB}\""]',
            '      POSTGRES_DB: ${DB_NAME}',
        ];
    }

    public function testAnUnknownValueStops(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown database 'Oracle'; expected none, mariadb, mysql, pgsql or sqlite");

        DatabaseChoice::select('Oracle');
    }

    public function testAPublishedComposeStubChangesTheService(): void
    {
        // A team publishes the stub of the service, for example to change the
        // image. The lookup finds the copy first.
        $copy = Stub::overridePath($this->root, Stub::SHARED, 'project-compose-mysql');

        mkdir(dirname($copy), 0o775, true);
        file_put_contents($copy, "  database:\n    image: mysql:9.1\n");

        $values = DatabaseChoice::MYSQL->values(new Stub(Paths::stubs(), $this->root), 'adr', 'shop');

        $this->assertSame("\n  database:\n    image: mysql:9.1\n", $values['databaseService']);
    }

    public function testAValueInAnyCaseIsAccepted(): void
    {
        $this->assertSame(DatabaseChoice::MARIADB, DatabaseChoice::select('MariaDB'));
        $this->assertSame(DatabaseChoice::NONE, DatabaseChoice::select('none'));
    }

    /**
     * @dataProvider servers
     */
    public function testEachServerHasItsServiceItsExtensionAndItsAdapter(
        DatabaseChoice $choice,
        string $image,
        string $port,
        string $extension,
        string $adapter,
        string $charset,
        string $volume,
        string $healthCheck,
        string $create
    ): void {
        $values = $this->values($choice, 'shop');

        $this->assertTrue($choice->hasService());
        $this->assertSame($extension, $choice->extension());
        $this->assertSame('project-provider-db', $choice->provider());
        $this->assertSame([], $choice->files());
        $this->assertSame($adapter, $values['adapter']);
        $this->assertSame($charset, $values['charset']);
        $this->assertSame($port, $values['databasePort']);
        $this->assertStringEndsWith('docker-php-ext-install ' . $extension . "\n", $values['databaseInstall']);
        $this->assertStringContainsString("\nDB_PORT=" . $port . "\n", $values['databaseEnv']);
        $this->assertStringContainsString("\nDB_PUBLISHED_PORT=" . $port . "\n", $values['databaseEnv']);

        $service = $values['databaseService'];

        // The service has the name SERVICE, and so has its volume.
        $this->assertStringStartsWith("\n  " . DatabaseChoice::SERVICE . ":\n", $service);
        $this->assertStringEndsWith("\nvolumes:\n  " . DatabaseChoice::SERVICE . ":\n", $service);
        $this->assertStringContainsString("\n    image: " . $image . "\n", $service);
        $this->assertStringContainsString(
            "\n      - \"127.0.0.1:\${DB_PUBLISHED_PORT:-" . $port . '}:' . $port . "\"\n",
            $service
        );
        $this->assertStringContainsString("\n      - database:" . $volume . "\n", $service);
        $this->assertStringContainsString("\n" . $healthCheck . "\n", $service);
        $this->assertStringContainsString("\n" . $create . "\n", $service);
    }

    public function testNoneAddsNothing(): void
    {
        $this->assertFalse(DatabaseChoice::NONE->hasService());
        $this->assertSame('', DatabaseChoice::NONE->extension());
        $this->assertSame([], DatabaseChoice::NONE->files());
        $this->assertNull(DatabaseChoice::NONE->provider());
        $this->assertSame(
            [
                'adapter'         => '',
                'charset'         => '',
                'databaseFile'    => '',
                'databaseIgnore'  => '',
                'databaseInstall' => '',
                'databaseName'    => '',
                'databasePort'    => '',
                'databaseUser'    => '',
                'dependsOn'       => '',
                'databaseEnv'     => '',
                'databaseService' => '',
            ],
            $this->values(DatabaseChoice::NONE, 'my-app')
        );
    }

    public function testTheMysqlValues(): void
    {
        // Asserted whole: these lines go into each MySQL project.
        $this->assertSame(
            [
                'adapter'         => 'Mysql',
                'charset'         => "            'charset'  => 'utf8mb4',\n",
                'databaseFile'    => '',
                'databaseIgnore'  => '',
                'databaseInstall' => "docker-php-ext-install pdo_mysql\n",
                'databaseName'    => 'my_app',
                'databasePort'    => '3306',
                'databaseUser'    => 'my_app',
                'dependsOn'       => "    depends_on:\n      database:\n        condition: service_healthy\n",
                'databaseEnv'     => "\n"
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
                'databaseService' => "\n"
                    . "  database:\n"
                    . "    image: mysql:8.4\n"
                    . "    container_name: \${PROJECT_PREFIX:-my-app}-database\n"
                    . "    environment:\n"
                    . "      MYSQL_DATABASE: \${DB_NAME}\n"
                    . "      MYSQL_USER: \${DB_USERNAME}\n"
                    . "      MYSQL_PASSWORD: \${DB_PASSWORD}\n"
                    . "      MYSQL_ROOT_PASSWORD: \${DB_PASSWORD}\n"
                    . "    ports:\n"
                    . "      - \"127.0.0.1:\${DB_PUBLISHED_PORT:-3306}:3306\"\n"
                    . "    volumes:\n"
                    . "      - database:/var/lib/mysql\n"
                    . "    healthcheck:\n"
                    . "      test: [\"CMD-SHELL\", \"mysqladmin ping -h 127.0.0.1 --silent\"]\n"
                    . "      interval: 5s\n"
                    . "      timeout: 5s\n"
                    . "      retries: 30\n"
                    . "\n"
                    . "volumes:\n"
                    . "  database:\n",
            ],
            $this->values(DatabaseChoice::MYSQL, 'my-app')
        );
    }

    public function testTheNamesComeFromTheProjectName(): void
    {
        // _ for -: SQL needs quotes for a name with -. Capitals and a leading
        // digit stay. The name is cut to 64 characters and the user to 32,
        // the limits of MySQL.
        $values = $this->values(DatabaseChoice::PGSQL, '1My-Shop');

        $this->assertSame('1My_Shop', $values['databaseName']);
        $this->assertSame('1My_Shop', $values['databaseUser']);

        $long = $this->values(DatabaseChoice::MYSQL, str_repeat('a', 70));

        $this->assertSame(str_repeat('a', 64), $long['databaseName']);
        $this->assertSame(str_repeat('a', 32), $long['databaseUser']);
    }

    public function testThePgsqlDockerfileInstallsLibpqFirst(): void
    {
        // pdo_pgsql needs the headers of libpq.
        $this->assertSame(
            "apt-get install -yq --no-install-recommends libpq-dev\ndocker-php-ext-install pdo_pgsql\n",
            $this->values(DatabaseChoice::PGSQL, 'shop')['databaseInstall']
        );
    }

    public function testTheQuestionHasEachChoiceOneTimeWithNoneFirst(): void
    {
        $this->assertSame(['none', 'mariadb', 'mysql', 'pgsql', 'sqlite'], DatabaseChoice::names());
    }

    public function testTheSqliteValues(): void
    {
        $this->assertFalse(DatabaseChoice::SQLITE->hasService());
        $this->assertSame('', DatabaseChoice::SQLITE->extension());
        $this->assertSame('project-provider-db-sqlite', DatabaseChoice::SQLITE->provider());
        $this->assertSame(
            ['storage/database.sqlite' => '', 'storage/.gitkeep' => ''],
            DatabaseChoice::SQLITE->files()
        );
        $this->assertSame(
            [
                'adapter'         => 'Sqlite',
                'charset'         => '',
                'databaseFile'    => 'storage/database.sqlite',
                'databaseIgnore'  => "/storage/database.sqlite\n",
                'databaseInstall' => '',
                'databaseName'    => '',
                'databasePort'    => '',
                'databaseUser'    => '',
                'dependsOn'       => '',
                'databaseEnv'     => "\n"
                    . "# The database: a SQLite file, relative to the project root. PHP on the host\n"
                    . "# does not read this file: there, the default in DbProvider applies.\n"
                    . "DB_NAME=storage/database.sqlite\n",
                'databaseService' => '',
            ],
            $this->values(DatabaseChoice::SQLITE, 'my-app')
        );
    }

    /**
     * The values with the packaged stubs, as new gives them for an ADR
     * project. The shared stubs are the same in each flavor.
     *
     * @return array<string, string>
     */
    private function values(DatabaseChoice $choice, string $project): array
    {
        return $choice->values(new Stub(Paths::stubs()), 'adr', $project);
    }
}
