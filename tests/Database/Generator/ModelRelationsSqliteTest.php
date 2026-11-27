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
use Crest\Tests\Support\Project\ListedReferencesSqlite;
use Phalcon\Db\Reference;

/**
 * The relations of a SQLite key that does not name the referenced columns
 * (spec 4.3): MySQL rejects it, and PostgreSQL uses the primary key. From
 * cphalcon 5.24.0 and phalcon/phalcon 6.0.0, Phalcon gives the primary key
 * (#87). Before these versions, Phalcon gives null for the column, and crest
 * skips the relation: ListedReferencesSqlite gives that form on each version.
 *
 * @group sqlite
 */
final class ModelRelationsSqliteTest extends AbstractModelRelationsTestCase
{
    public function testADuplicateAliasSkipsAllItsRelations(): void
    {
        // The belongsTo() alias Customer and the hasMany() alias CUSTOMER are
        // one alias for Phalcon, so crest skips both. SQLite gives the last
        // key first, so the key of billing_id comes first: a skipped relation
        // comes before the others, and the count of the aliases must go on.
        $this->createTables(
            CustomersMigration::class,
            InvoicesWithBillingMigration::class,
            CustomerInCapitalsMigration::class
        );

        $adapter = $this->oldAdapter(
            [
                'invoices' => [
                    $this->key('foreign_key_0', 'billing_id', 'customers', null),
                    $this->key('foreign_key_1', 'customer_id', 'customers', 'id'),
                ],
                'CUSTOMER' => [$this->key('foreign_key_0', 'invoice_id', 'invoices', 'id')],
            ]
        );

        $relations = $this->relationsOf($adapter, 'invoices', ['id', 'customer_id', 'billing_id']);

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

    public function testAKeyWithNoReferencedColumnsGivesThePrimaryKey(): void
    {
        $this->skipKeyWithNoColumnsOnOldCphalcon();

        // SQLite accepts a key with no column: it points to the primary key
        // of the table (#87).
        $this->createTables(CustomersMigration::class, InvoicesTwoKeysNoColumnsMigration::class);

        $relations = $this->relationsOf($this->adapter(), 'invoices', ['id', 'billing_id', 'customer_id']);

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['billing_id'],
                    '\\App\\Models\\Customers',
                    ['id'],
                    'Billing',
                    'belongsTo invoices(billing_id) -> customers(id)'
                ),
                $this->relation(
                    'belongs-to',
                    ['customer_id'],
                    '\\App\\Models\\Customers',
                    ['id'],
                    'Customer',
                    self::INVOICE_KEY
                ),
            ],
            $relations->relations
        );
        $this->assertSame([], $relations->skipped);
    }

    public function testAKeyWithNoReferencedColumnsIsSkipped(): void
    {
        // Before cphalcon 5.24.0, Phalcon gives null for the column. SQLite
        // gives the last key first, so the key of customer_id comes first, and
        // the sort of the lines must change the order.
        $this->createTables(CustomersMigration::class, InvoicesTwoKeysNoColumnsMigration::class);

        $adapter = $this->oldAdapter(
            [
                'invoices' => [
                    $this->key('foreign_key_0', 'customer_id', 'customers', null),
                    $this->key('foreign_key_1', 'billing_id', 'customers', null),
                ],
            ]
        );

        $relations = $this->relationsOf($adapter, 'invoices', ['id', 'billing_id', 'customer_id']);

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
            $this->oldAdapter(['invoices' => [$this->key('foreign_key_0', 'customer_id', 'customers', null)]]),
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

    /**
     * A key with one column. A null referenced column is a key with no
     * referenced columns, as Phalcon gave it before cphalcon 5.24.0.
     */
    private function key(string $name, string $column, string $referencedTable, ?string $referenced): Reference
    {
        return new Reference(
            $name,
            [
                'columns'           => [$column],
                'referencedTable'   => $referencedTable,
                'referencedColumns' => [$referenced],
            ]
        );
    }

    /**
     * The SQLite adapter of the suite, with the keys of the list.
     *
     * @param array<string, list<Reference>> $references The table name, and its keys
     */
    private function oldAdapter(array $references): ListedReferencesSqlite
    {
        return new ListedReferencesSqlite($this->getSettings()->getDatabaseOptions('sqlite'), $references);
    }
}
