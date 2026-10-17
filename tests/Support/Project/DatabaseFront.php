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
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;

/**
 * An MVC front whose `db` service is the database of the suite
 * (TestDatabase), as a project from `crest new` with a database provider has.
 * So make:model reads the database of the test.
 */
final class DatabaseFront
{
    public function boot(): DiInterface
    {
        $container = new FactoryDefault();

        // Not a static closure: the container binds each closure to itself.
        $container->setShared('db', function (): AdapterInterface {
            return TestDatabase::adapter();
        });

        return $container;
    }
}
