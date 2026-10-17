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

use Crest\Tests\Support\Migrations\CustomerInCapitalsMigration;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\InvoicesOneKeyNoColumnsMigration;
use Crest\Tests\Support\Migrations\InvoicesTwoKeysNoColumnsMigration;
use Crest\Tests\Support\Migrations\InvoicesWithBillingMigration;
use Crest\Tests\Support\Migrations\LinesMigration;
use Crest\Tests\Support\Migrations\ShipmentsMigration;
use Crest\Tests\Support\Project\CompositeKeySqlite;
use Phalcon\Db\Reference;

/**
 * The relations that only SQLite gives (spec 4.3): a key that does not name
 * the referenced columns (MySQL rejects it, and PostgreSQL uses the primary
 * key), and a composite key that CompositeKeySqlite gives as one reference.
 *
 * @group sqlite
 */
final class ModelRelationsSqliteTest extends AbstractModelRelationsTestCase
{
    public function testACompositeKeyGivesArraysAndTheClassAsTheAlias(): void
    {
        // SQLite splits a composite key. CompositeKeySqlite gives one
        // reference with two columns, as MySQL does. The first column ends in
        // _id, so an alias from the column would be Order.
        $this->createTables(LinesMigration::class, ShipmentsMigration::class);

        $adapter = new CompositeKeySqlite(
            $this->getSettings()->getDatabaseOptions('sqlite'),
            [
                'shipments' => [
                    new Reference(
                        'fk_shipments_lines',
                        [
                            'columns'           => ['order_id', 'line_no'],
                            'referencedTable'   => 'lines',
                            'referencedColumns' => ['order_id', 'number'],
                        ]
                    ),
                ],
            ]
        );

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

    public function testADuplicateAliasSkipsAllItsRelations(): void
    {
        // The belongsTo() alias Customer and the hasMany() alias CUSTOMER are
        // one alias for Phalcon, so crest skips both. billing_id comes after
        // customer_id, so SQLite gives it first: a skipped relation comes
        // before the others, and the count of the aliases must go on.
        $this->createTables(
            CustomersMigration::class,
            InvoicesWithBillingMigration::class,
            CustomerInCapitalsMigration::class
        );

        $relations = $this->relationsOf($this->adapter(), 'invoices', ['id', 'customer_id', 'billing_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo invoices(billing_id) -> customers(): '
                . 'the key does not name the referenced columns',
                'Skipped the relation ' . self::INVOICE_KEY . ': two or more relations have the alias Customer',
                'Skipped the relation hasMany CUSTOMER(invoice_id) -> invoices(id): '
                . 'two or more relations have the alias CUSTOMER',
            ],
            $relations->skipped
        );
    }

    public function testAKeyWithNoReferencedColumnsIsSkipped(): void
    {
        // SQLite accepts a key with no column, and gives NULL for the column.
        // billing_id comes first, so SQLite gives customer_id first, and the
        // sort of the lines must change the order.
        $this->createTables(CustomersMigration::class, InvoicesTwoKeysNoColumnsMigration::class);

        $relations = $this->relationsOf($this->adapter(), 'invoices', ['id', 'billing_id', 'customer_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo invoices(billing_id) -> customers(): '
                . 'the key does not name the referenced columns',
                'Skipped the relation belongsTo invoices(customer_id) -> customers(): '
                . 'the key does not name the referenced columns',
            ],
            $relations->skipped
        );
    }

    public function testAnAttributeForASkippedRelationIsDropped(): void
    {
        // Check 0 comes before the alias, so the attribute does nothing, and
        // crest drops it with a line.
        $this->createTables(CustomersMigration::class, InvoicesOneKeyNoColumnsMigration::class);

        $relations = $this->relationsOf(
            $this->adapter(),
            'invoices',
            ['id', 'customer_id'],
            ['belongsTo invoices(customer_id) -> customers()' => ['alias' => 'Client', 'line' => 16]]
        );

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo invoices(customer_id) -> customers(): '
                . 'the key does not name the referenced columns',
            ],
            $relations->skipped
        );
        $this->assertSame(
            [
                "Dropped the alias Client of 'belongsTo invoices(customer_id) -> customers()': "
                . 'crest skips the relation',
            ],
            $relations->dropped
        );
    }
}
