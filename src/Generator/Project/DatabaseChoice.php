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

namespace Crest\Generator\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Generator\Stub;

use function array_column;
use function array_diff;
use function dirname;
use function sprintf;
use function str_replace;
use function strtolower;
use function substr;

/**
 * The database of a project from `crest new --database` (#54): what each
 * choice puts in the project. A server database (mysql, mariadb, pgsql) gets
 * a docker compose service, a PDO extension and connection values. sqlite is
 * a file in the project, with no service. none adds nothing: each value is
 * empty, and the files are the same as without the option.
 *
 * The name is not Database: Crest\Project\Database is the connection of
 * make:model.
 */
enum DatabaseChoice: string
{
    case MARIADB = 'mariadb';
    case MYSQL   = 'mysql';
    case NONE    = 'none';
    case PGSQL   = 'pgsql';
    case SQLITE  = 'sqlite';

    /**
     * The docker compose service of a server database. It is also the value
     * of DB_HOST in .env: compose gives each service a host name.
     */
    public const SERVICE = 'database';

    /**
     * The SQLite database, relative to the project root.
     */
    public const SQLITE_FILE = 'storage/database.sqlite';

    /**
     * The app service waits for the health check of the database. Thus
     * make:model can run right after `crest up`.
     */
    private const DEPENDS_ON = "    depends_on:\n"
        . '      ' . self::SERVICE . ":\n"
        . "        condition: service_healthy\n";

    /**
     * The longest database name of MySQL and MariaDB. PostgreSQL cuts a
     * longer name to 63 characters itself.
     */
    private const NAME_LENGTH = 64;

    /**
     * The longest user name of MySQL.
     */
    private const USER_LENGTH = 32;

    /**
     * The choices, in the order of the question: none first, because it is
     * the default, then the other cases in their order. php-cs-fixer keeps
     * the cases in alphabetical order.
     *
     * @return non-empty-list<string>
     */
    public static function names(): array
    {
        $names = array_diff(array_column(self::cases(), 'value'), [self::NONE->value]);

        return [self::NONE->value, ...$names];
    }

    /**
     * The choice of a given value, in any case, as --runtime takes it.
     */
    public static function select(string $given): self
    {
        return self::tryFrom(strtolower($given))
            ?? throw new Exception(
                sprintf("unknown database '%s'; expected none, mariadb, mysql, pgsql or sqlite", $given)
            );
    }

    /**
     * The PDO extension of a server database, for the Dockerfile and for PHP
     * on the host. The php image has pdo_sqlite.
     */
    public function extension(): string
    {
        return match ($this) {
            self::MARIADB, self::MYSQL => 'pdo_mysql',
            self::NONE, self::SQLITE   => '',
            self::PGSQL                => 'pdo_pgsql',
        };
    }

    /**
     * The files that no stub renders, path => contents. An empty file is a
     * valid SQLite database. .gitignore keeps the database out of git, so
     * .gitkeep keeps its folder: SQLite cannot create a file in a folder that
     * does not exist.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        if (self::SQLITE !== $this) {
            return [];
        }

        return [
            self::SQLITE_FILE                        => '',
            dirname(self::SQLITE_FILE) . '/.gitkeep' => '',
        ];
    }

    public function hasService(): bool
    {
        return match ($this) {
            self::MARIADB, self::MYSQL, self::PGSQL => true,
            self::NONE, self::SQLITE                => false,
        };
    }

    /**
     * The project stub of DbProvider. The SQLite provider gets the project
     * root, because DB_NAME is relative to it. Null for none.
     */
    public function provider(): ?string
    {
        return match ($this) {
            self::MARIADB, self::MYSQL, self::PGSQL => Stub::PROJECT_PREFIX . 'provider-db',
            self::NONE                              => null,
            self::SQLITE                            => Stub::PROJECT_PREFIX . 'provider-db-sqlite',
        };
    }

    /**
     * The values of the project stubs: the shared stubs (compose, Dockerfile,
     * .env, .gitignore) and the DbProvider stubs. Each value is empty for
     * none, and a value that a choice does not use is empty too. The compose
     * service and the .env lines come from project stubs of their own, so a
     * team can publish one, for example to change an image.
     *
     * @return array<string, string>
     */
    public function values(Stub $stub, string $flavor, string $project): array
    {
        $server = $this->hasService();
        $name   = str_replace('-', '_', $project);
        $values = [
            'adapter'         => $this->adapter(),
            'charset'         => $this->charset(),
            'databaseFile'    => self::SQLITE === $this ? self::SQLITE_FILE : '',
            'databaseIgnore'  => self::SQLITE === $this ? '/' . self::SQLITE_FILE . "\n" : '',
            'databaseInstall' => $this->install(),
            'databaseName'    => true === $server ? substr($name, 0, self::NAME_LENGTH) : '',
            'databasePort'    => $this->port(),
            'databaseUser'    => true === $server ? substr($name, 0, self::USER_LENGTH) : '',
            'dependsOn'       => true === $server ? self::DEPENDS_ON : '',
        ];

        return [
            ...$values,
            'databaseEnv'     => $this->env($stub, $flavor, $values),
            'databaseService' => $this->service($stub, $flavor, $project),
        ];
    }

    /**
     * The class of the adapter in Phalcon\Db\Adapter\Pdo.
     */
    private function adapter(): string
    {
        return match ($this) {
            self::MARIADB, self::MYSQL => 'Mysql',
            self::NONE                 => '',
            self::PGSQL                => 'Postgresql',
            self::SQLITE               => 'Sqlite',
        };
    }

    /**
     * The charset line of the connection, for the MySQL adapter.
     */
    private function charset(): string
    {
        return match ($this) {
            self::MARIADB, self::MYSQL            => "            'charset'  => 'utf8mb4',\n",
            self::NONE, self::PGSQL, self::SQLITE => '',
        };
    }

    /**
     * The .env lines, after a blank line. They do not depend on the runtime:
     * docker compose gives them to the app container, and PHP on the host
     * uses the defaults of DbProvider.
     *
     * @param array<string, string> $values
     */
    private function env(Stub $stub, string $flavor, array $values): string
    {
        $name = match ($this) {
            self::MARIADB, self::MYSQL, self::PGSQL => 'env-database',
            self::NONE                              => null,
            self::SQLITE                            => 'env-sqlite',
        };

        return null === $name ? '' : "\n" . $stub->render($flavor, Stub::PROJECT_PREFIX . $name, $values);
    }

    /**
     * The lines of the Dockerfile that install the PDO extension, in the RUN
     * of the system packages. pdo_pgsql needs the headers of libpq.
     */
    private function install(): string
    {
        return match ($this) {
            self::MARIADB, self::MYSQL => "docker-php-ext-install pdo_mysql\n",
            self::NONE, self::SQLITE   => '',
            self::PGSQL                => "apt-get install -yq --no-install-recommends libpq-dev\n"
                . "docker-php-ext-install pdo_pgsql\n",
        };
    }

    private function port(): string
    {
        return match ($this) {
            self::MARIADB, self::MYSQL => '3306',
            self::NONE, self::SQLITE   => '',
            self::PGSQL                => '5432',
        };
    }

    /**
     * The compose service of a server database, after a blank line, with its
     * named volume. The service gets its values from .env, so the stub has
     * only {{ project }}.
     */
    private function service(Stub $stub, string $flavor, string $project): string
    {
        if (false === $this->hasService()) {
            return '';
        }

        return "\n" . $stub->render(
            $flavor,
            Stub::PROJECT_PREFIX . 'compose-' . $this->value,
            ['project' => $project]
        );
    }
}
