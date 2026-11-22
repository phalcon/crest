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

use Crest\Tests\Support\TestDatabase;
use Phalcon\Container\Container;
use Phalcon\DataMapper\Pdo\Connection;

/**
 * An ADR front whose `db` service is a DataMapper connection to the database
 * of the suite (TestDatabase), as a project from `crest new --database` has
 * (#84). So make:repository reads the database of the test.
 */
final class DataMapperFront
{
    public function boot(): Container
    {
        $container = new Container();

        $container->set(Connection::class, static fn (): Connection => TestDatabase::connection());
        $container->setAlias(Connection::class, 'db');

        return $container;
    }
}
