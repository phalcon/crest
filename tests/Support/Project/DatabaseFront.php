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

use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;

/**
 * An MVC front whose `db` service is an SQLite file in the project root, as a
 * project from `crest new` with a database provider has.
 */
final class DatabaseFront
{
    public const FILE = 'database.sqlite';

    public function __construct(private readonly string $root)
    {
    }

    public function boot(): DiInterface
    {
        $container = new FactoryDefault();
        $file      = $this->root . '/' . self::FILE;

        // Not a static closure: the container binds each closure to itself.
        $container->setShared('db', function () use ($file): Sqlite {
            return new Sqlite(['dbname' => $file]);
        });

        return $container;
    }
}
