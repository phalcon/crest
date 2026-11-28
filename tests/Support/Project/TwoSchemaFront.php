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

use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;
use Phalcon\Talon\Talon;

/**
 * An MVC front whose `db` service is a TwoSchemaSqlite on the SQLite file of
 * the suite: a server with two schemas that have the same tables (#86).
 */
final class TwoSchemaFront
{
    public function boot(): DiInterface
    {
        $container  = new FactoryDefault();
        $descriptor = Talon::settings()->getDatabaseOptions('sqlite');

        // Not a static closure: the container binds each closure to itself.
        $container->setShared('db', function () use ($descriptor): TwoSchemaSqlite {
            return new TwoSchemaSqlite($descriptor);
        });

        return $container;
    }
}
