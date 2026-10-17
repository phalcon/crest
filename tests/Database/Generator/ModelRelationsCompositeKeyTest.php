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

namespace Crest\Tests\Database\Generator;

use Crest\Tests\Support\Migrations\LinesMigration;
use Crest\Tests\Support\Migrations\ShipmentsMigration;

/**
 * A composite key on MySQL, MariaDB and PostgreSQL (spec 4.4). These
 * databases give one reference with each column of the key. SQLite gives one
 * reference for each column, so ModelRelationsSqliteTest gives the key with
 * CompositeKeySqlite.
 *
 * @group mysql
 * @group pgsql
 */
final class ModelRelationsCompositeKeyTest extends AbstractModelRelationsTestCase
{
    public function testACompositeKeyGivesArraysAndTheClassAsTheAlias(): void
    {
        $this->skipCompositeKeyOnOldCphalcon();

        // The first column ends in _id, so an alias from the column would be
        // Order.
        $this->createTables(LinesMigration::class, ShipmentsMigration::class);

        $adapter = $this->adapter();

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['order_id', 'line_no'],
                    '\\App\\Models\\Lines',
                    ['order_id', 'number'],
                    'Lines',
                    'belongsTo shipments(order_id, line_no) -> lines(order_id, number)'
                ),
            ],
            $this->relationsOf($adapter, 'shipments', ['id', 'order_id', 'line_no'])->relations
        );
        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['order_id', 'number'],
                    '\\App\\Models\\Shipments',
                    ['order_id', 'line_no'],
                    'Shipments',
                    'hasMany shipments(order_id, line_no) -> lines(order_id, number)'
                ),
            ],
            $this->relationsOf($adapter, 'lines', ['order_id', 'number'])->relations
        );
    }
}
