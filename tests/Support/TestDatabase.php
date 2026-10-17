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

use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Db\Adapter\Pdo\Postgresql;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Talon\Talon;

use function getenv;

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
        $driver     = getenv('driver');
        $driver     = false === $driver ? 'sqlite' : $driver;
        $descriptor = Talon::settings()->getDatabaseOptions($driver);

        return match ($driver) {
            'mariadb', 'mysql' => new Mysql($descriptor),
            'pgsql'            => new Postgresql($descriptor),
            default            => new Sqlite($descriptor),
        };
    }
}
