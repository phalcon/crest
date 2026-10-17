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

use Phalcon\Db\Reference;
use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;
use Phalcon\Talon\Talon;

/**
 * An MVC front whose `db` service is a CompositeKeySqlite on the SQLite file
 * of the suite. The table `shipments` has one key with two columns to the
 * table `lines`, as MySQL gives it.
 */
final class CompositeKeyFront
{
    public function boot(): DiInterface
    {
        $container  = new FactoryDefault();
        $descriptor = Talon::settings()->getDatabaseOptions('sqlite');

        // Not a static closure: the container binds each closure to itself.
        $container->setShared('db', function () use ($descriptor): CompositeKeySqlite {
            $key = new Reference(
                'fk_shipments_lines',
                [
                    'columns'           => ['order_id', 'line_no'],
                    'referencedTable'   => 'lines',
                    'referencedColumns' => ['order_id', 'number'],
                ]
            );

            return new CompositeKeySqlite($descriptor, ['shipments' => [$key]]);
        });

        return $container;
    }
}
