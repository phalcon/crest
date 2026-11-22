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

namespace Crest\Tests\Support\Project;

use Phalcon\Container\Container;
use Phalcon\DataMapper\Pdo\Connection;

/**
 * An ADR front whose `db` service is a DataMapper connection to a SQLite
 * database in memory, with three tables: users (one column in the primary
 * key), pairs (two columns in the primary key) and notes (no primary key).
 * The unit suite runs make:repository with it (#84).
 */
final class MemoryConnectionFront
{
    public function boot(): Container
    {
        $container = new Container();

        $container->set(
            Connection::class,
            static function (): Connection {
                $connection = new Connection('sqlite::memory:');

                $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT)');
                $connection->exec('CREATE TABLE pairs (a INTEGER, b INTEGER, PRIMARY KEY (a, b))');
                $connection->exec('CREATE TABLE notes (body TEXT)');

                return $connection;
            }
        );
        $container->setAlias(Connection::class, 'db');

        return $container;
    }
}
