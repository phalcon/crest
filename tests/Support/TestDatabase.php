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

namespace Crest\Tests\Support;

use Phalcon\DataMapper\Pdo\Connection;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Db\Adapter\Pdo\Postgresql;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Talon\Talon;

use function getenv;
use function is_string;

/**
 * The database of a database suite, for Phalcon. The phpunit file of the
 * suite gives the driver (the environment value `driver`), and talon gives
 * the settings of the driver (the DATA_* values). MariaDB uses the MySQL
 * adapter.
 */
final class TestDatabase
{
    /**
     * A new Phalcon adapter for the database of the suite.
     */
    public static function adapter(): AdapterInterface
    {
        $driver     = self::driver();
        $descriptor = Talon::settings()->getDatabaseOptions($driver);

        return match ($driver) {
            'mariadb', 'mysql' => new Mysql($descriptor),
            'pgsql'            => new Postgresql($descriptor),
            default            => new Sqlite($descriptor),
        };
    }

    /**
     * A new DataMapper connection to the database of the suite, as the `db`
     * service of an ADR project has (#84).
     */
    public static function connection(): Connection
    {
        $driver   = self::driver();
        $settings = Talon::settings();
        $options  = $settings->getDatabaseOptions($driver);
        $username = $options['username'] ?? null;
        $password = $options['password'] ?? null;

        return new Connection(
            $settings->getDatabaseDsn($driver),
            true === is_string($username) ? $username : null,
            true === is_string($password) ? $password : null
        );
    }

    private static function driver(): string
    {
        $driver = getenv('driver');

        return false === $driver ? 'sqlite' : $driver;
    }
}
