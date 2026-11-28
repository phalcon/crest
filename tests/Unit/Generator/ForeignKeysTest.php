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

namespace Crest\Tests\Unit\Generator;

use Crest\Generator\ForeignKeys;
use Crest\Generator\ModelNames;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Reference;
use PHPUnit\Framework\TestCase;

use function array_key_first;
use function strtolower;

/**
 * The key map of make:model (#52, spec 3.3), with no database. The keys to a
 * table of another schema (#69, spec 3.6) use a stub adapter, so the unit
 * suite holds them too.
 */
final class ForeignKeysTest extends TestCase
{
    public function testElsewhereGivesNothingForATableWithNoSuchKeys(): void
    {
        $this->assertSame([], (new ForeignKeys(['invoices' => []]))->elsewhere('invoices'));
    }

    public function testOfGivesNothingForATableThatTheMapDoesNotHave(): void
    {
        $this->assertSame([], (new ForeignKeys(['customers' => []]))->of('invoices'));
    }

    public function testOfGivesTheKeysOfTheTable(): void
    {
        $reference = new Reference(
            'invoices_customer',
            ['referencedTable' => 'customers', 'columns' => ['customer_id'], 'referencedColumns' => ['id']]
        );

        $this->assertSame(
            [$reference],
            (new ForeignKeys(['customers' => [], 'invoices' => [$reference]]))->of('invoices')
        );
    }

    public function testReadAlsoReadsTheTypedNameOfTheTable(): void
    {
        // MySQL with lower_case_table_names 1 or 2 finds a table without
        // regard to case, but listTables() gives the stored name. The keys of
        // the name that the user typed come from the server, as before #52.
        // Phalcon gives the keys by name, and the map keeps a list.
        $reference = new Reference(
            'invoices_customer',
            ['referencedTable' => 'customers', 'columns' => ['customer_id'], 'referencedColumns' => ['id']]
        );
        $adapter   = $this->createStub(AdapterInterface::class);

        $adapter->method('listTables')->willReturn(['customers', 'invoices']);
        $adapter->method('describeReferences')->willReturnCallback(
            static fn (string $table): array => 'invoices' === strtolower($table)
                ? ['invoices_customer' => $reference]
                : []
        );

        $this->assertSame([$reference], ForeignKeys::read($adapter, 'Invoices')->of('Invoices'));
    }

    public function testReadComparesTheTableNamesWithoutRegardToCase(): void
    {
        // MySQL with lower_case_table_names 1 or 2 can give the referenced
        // table in another case than listTables() gives it, in each
        // direction.
        $key  = $this->key('invoices', 'customer_id', 'customers');
        $keys = ForeignKeys::read($this->adapter('mysql', ['Customers', 'invoices'], ['invoices' => [$key]]));

        $this->assertSame([$key], $keys->of('invoices'));
        $this->assertSame([], $keys->elsewhere('invoices'));

        $key  = $this->key('invoices', 'customer_id', 'Customers');
        $keys = ForeignKeys::read($this->adapter('mysql', ['customers', 'invoices'], ['invoices' => [$key]]));

        $this->assertSame([$key], $keys->of('invoices'));
        $this->assertSame([], $keys->elsewhere('invoices'));
    }

    public function testReadGivesAListForTheTypedNameOnSqlite(): void
    {
        // Phalcon gives the keys by name. SQLite finds a table without regard
        // to case, so a typed name in another case gets its keys from the
        // server, and of() gives them as a list.
        $key     = $this->key('invoices', 'customer_id', 'customers');
        $adapter = $this->createStub(AdapterInterface::class);

        $adapter->method('getType')->willReturn('sqlite');
        $adapter->method('listTables')->willReturn(['invoices']);
        $adapter->method('describeReferences')->willReturnCallback(
            static fn (string $table): array => 'Invoices' === $table ? ['invoices_customer_id' => $key] : []
        );

        $this->assertSame([$key], ForeignKeys::read($adapter, 'Invoices')->of('Invoices'));
    }

    public function testReadGivesTheSchemaToEachRead(): void
    {
        // Also to the read of a typed name that listTables() does not give.
        $key     = $this->key('bills', 'vendor_id', 'vendors');
        $adapter = $this->createStub(AdapterInterface::class);

        $adapter->method('getType')->willReturn('pgsql');
        $adapter->method('listTables')->willReturnCallback(
            static fn (?string $schema = null): array => 'billing' === $schema ? ['bills', 'vendors'] : []
        );
        $adapter->method('describeReferences')->willReturnCallback(
            static fn (string $table, ?string $schema = null): array => 'bills' === strtolower($table)
                && 'billing' === $schema ? [$key] : []
        );

        $names = ModelNames::of(['schema' => 'billing']);

        $this->assertSame([$key], ForeignKeys::read($adapter, '', $names)->of('bills'));
        $this->assertSame([$key], ForeignKeys::read($adapter, 'Bills', $names)->of('Bills'));
    }

    public function testReadKeepsEachKeyOnSqlite(): void
    {
        // SQLite has no schemas: a key to a table that does not exist stays
        // (spec 3.6).
        $key  = $this->key('invoices', 'customer_id', 'customers');
        $keys = ForeignKeys::read($this->adapter('sqlite', ['invoices'], ['invoices' => [$key]]));

        $this->assertSame([$key], $keys->of('invoices'));
        $this->assertSame([], $keys->elsewhere('invoices'));
    }

    public function testReadMatchesTheReferencedSchemaWithoutRegardToCase(): void
    {
        // MySQL with lower_case_table_names 1 or 2 can give the schema in
        // another case. The name has the case of the list.
        $keys = ForeignKeys::read(
            $this->schemaAdapter(
                ['public' => ['receipts'], 'Billing' => ['customers']],
                ['receipts' => [$this->schemaKey('receipts', 'customer_id', 'BILLING', 'customers')]]
            ),
            '',
            ModelNames::of(['schema' => ['public', 'Billing']])
        );

        $this->assertSame('Billing.customers', $keys->of('receipts')[0]->getReferencedTable());
    }

    public function testReadPutsAKeyToAnotherSchemaElsewhere(): void
    {
        $key  = $this->key('invoices', 'customer_id', 'customers');
        $keys = ForeignKeys::read($this->adapter('mysql', ['invoices'], ['invoices' => [$key]]));

        $this->assertSame([], $keys->of('invoices'));
        $this->assertSame([$key], $keys->elsewhere('invoices'));
        $this->assertSame([], $keys->related('invoices'));
        $this->assertSame(['invoices'], $keys->tables());
    }

    public function testReadSplitsTheKeysOfTheTypedName(): void
    {
        $key  = $this->key('Bills', 'customer_id', 'customers');
        $keys = ForeignKeys::read($this->adapter('mysql', ['bills', 'vendors'], ['Bills' => [$key]]), 'Bills');

        $this->assertSame([$key], $keys->elsewhere('Bills'));
        $this->assertSame([], $keys->of('Bills'));
    }

    public function testReadWithAListGivesTheKeysOfEachSchema(): void
    {
        $keys = ForeignKeys::read(
            $this->schemaAdapter(
                ['public' => ['customers'], 'billing' => ['payments']],
                ['billing.payments' => [$this->schemaKey('payments', 'customer_id', 'public', 'customers')]]
            ),
            '',
            ModelNames::of(['schema' => ['public', 'billing']])
        );

        $this->assertCount(1, $keys->of('billing.payments'));
        $this->assertSame('customers', $keys->of('billing.payments')[0]->getReferencedTable());
        $this->assertSame([], $keys->elsewhere('billing.payments'));
        $this->assertSame(['customers', 'billing.payments'], $keys->tables());
        $this->assertSame(['billing.payments'], $keys->related('customers'));
    }

    public function testReadWithAListKeepsANumericTableName(): void
    {
        // PHP makes the key '2024' an integer. A key with no referenced
        // schema points to the schema of its table.
        $keys = ForeignKeys::read(
            $this->schemaAdapter(
                ['public' => ['2024', 'customers'], 'billing' => []],
                ['2024' => [$this->key('2024', 'customer_id', 'customers')]]
            ),
            '',
            ModelNames::of(['schema' => ['public', 'billing']])
        );

        $this->assertSame('customers', $keys->of('2024')[0]->getReferencedTable());
    }

    public function testReadWithAListNamesATableOfAnotherSchema(): void
    {
        $keys = ForeignKeys::read(
            $this->schemaAdapter(
                ['public' => ['receipts'], 'billing' => ['customers']],
                ['receipts' => [$this->schemaKey('receipts', 'customer_id', 'billing', 'customers')]]
            ),
            '',
            ModelNames::of(['schema' => ['public', 'billing']])
        );
        $key  = $keys->of('receipts')[0];

        $this->assertSame('billing.customers', $key->getReferencedTable());
        $this->assertSame('billing', $key->getReferencedSchema());
        $this->assertSame('receipts_customer_id', $key->getName());
        $this->assertSame(['customer_id'], $key->getColumns());
        $this->assertSame(['id'], $key->getReferencedColumns());
        $this->assertSame('CASCADE', $key->getOnDelete());
        $this->assertSame('RESTRICT', $key->getOnUpdate());
        $this->assertSame('crest', $key->getSchemaName());
        $this->assertSame(['billing.customers'], $keys->related('receipts'));
    }

    public function testReadWithAListOfOneIsTheStringRead(): void
    {
        // Before cphalcon 5.24.0, PostgreSQL gives the database as the
        // referenced schema. One schema does not use it.
        $key  = $this->schemaKey('bills', 'vendor_id', 'crest', 'vendors');
        $keys = ForeignKeys::read(
            $this->schemaAdapter(['billing' => ['bills', 'vendors']], ['bills' => [$key]]),
            '',
            ModelNames::of(['schema' => ['billing']])
        );

        $this->assertSame([$key], $keys->of('bills'));
        $this->assertSame([], $keys->elsewhere('bills'));
    }

    public function testReadWithAListPutsAKeyToASchemaNotInTheListElsewhere(): void
    {
        $keys = ForeignKeys::read(
            $this->schemaAdapter(
                ['public' => ['receipts'], 'billing' => []],
                [
                    'receipts' => [
                        $this->schemaKey('receipts', 'customer_id', 'crm', 'customers'),
                        $this->schemaKey('receipts', 'buyer_id', 'billing', 'customers'),
                    ],
                ]
            ),
            '',
            ModelNames::of(['schema' => ['public', 'billing']])
        );

        // The key after the key to crm stays a key of the list.
        $this->assertCount(1, $keys->of('receipts'));
        $this->assertSame('billing.customers', $keys->of('receipts')[0]->getReferencedTable());
        $this->assertCount(1, $keys->elsewhere('receipts'));
        $this->assertSame('crm.customers', $keys->elsewhere('receipts')[0]->getReferencedTable());
    }

    public function testReadWithAListReadsATypedNameOfAnotherSchema(): void
    {
        // A name that listTables() does not give, for example in another
        // case on MySQL, gets its keys from the server.
        $keys = ForeignKeys::read(
            $this->schemaAdapter(
                ['public' => ['customers'], 'billing' => ['payments']],
                ['billing.Payments' => [$this->schemaKey('Payments', 'customer_id', 'public', 'customers')]]
            ),
            'billing.Payments',
            ModelNames::of(['schema' => ['public', 'billing']])
        );

        $this->assertSame('customers', $keys->of('billing.Payments')[0]->getReferencedTable());
    }

    public function testReadWithAListTakesAKeyWithNoReferencedSchemaToItsOwnSchema(): void
    {
        $keys = ForeignKeys::read(
            $this->schemaAdapter(
                ['public' => [], 'billing' => ['bills', 'vendors']],
                ['billing.bills' => [$this->key('bills', 'vendor_id', 'vendors')]]
            ),
            '',
            ModelNames::of(['schema' => ['public', 'billing']])
        );

        $this->assertSame('billing.vendors', $keys->of('billing.bills')[0]->getReferencedTable());
    }

    public function testRelatedGivesTheOtherTablesInBothDirections(): void
    {
        $keys = $this->map();

        $this->assertSame(['customers', 'payments'], $keys->related('invoices'));
        // Two keys of orders point to customers: orders is in the list one time.
        $this->assertSame(['invoices', 'orders'], $keys->related('customers'));
        // A key that points to its own table gives no other table.
        $this->assertSame([], $keys->related('notes'));
    }

    public function testTablesGivesANumericNameAsAString(): void
    {
        // PHP makes the key '2024' an integer. ModelRelations gives each name
        // to of(string), so tables() must give strings.
        $this->assertSame(['2024', 'users'], (new ForeignKeys(['2024' => [], 'users' => []]))->tables());
    }

    public function testTablesHasTheViewsOnEachDriver(): void
    {
        // SQLite does not list a view as a table, the servers do (#88). A
        // name in both lists is in the map one time.
        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('getType')->willReturn('sqlite');
        $adapter->method('listTables')->willReturn(['customers']);
        $adapter->method('listViews')->willReturn(['customer_names']);

        $this->assertSame(['customers', 'customer_names'], ForeignKeys::read($adapter)->tables());

        $server = $this->createStub(AdapterInterface::class);
        $server->method('getType')->willReturn('mysql');
        $server->method('listTables')->willReturn(['customers', 'customer_names']);
        $server->method('listViews')->willReturn(['customer_names']);

        $this->assertSame(['customers', 'customer_names'], ForeignKeys::read($server)->tables());
    }

    public function testTablesKeepsTheOrderOfTheMap(): void
    {
        $this->assertSame(['users', 'customers'], (new ForeignKeys(['users' => [], 'customers' => []]))->tables());
    }

    public function testUsingGivesTheTablesThatUseTheColumns(): void
    {
        $keys = $this->map();

        $this->assertSame([], $keys->using('customers', ['name']));
        $this->assertSame(['invoices', 'orders'], $keys->using('customers', ['id']));
        $this->assertSame(['customers'], $keys->using('invoices', ['customer_id']));
        $this->assertSame(['payments'], $keys->using('invoices', ['id']));
        $this->assertSame(['customers', 'payments'], $keys->using('invoices', ['id', 'customer_id']));
        $this->assertSame([], $keys->using('invoices', ['total']));
        $this->assertSame([], $keys->using('notes', ['id', 'parent_id']));
    }

    public function testUsingSortsTheTables(): void
    {
        // payments comes before invoices in the map; the result is sorted.
        $keys = new ForeignKeys(
            [
                'payments'  => [$this->key('payments', 'customer_id', 'customers')],
                'invoices'  => [$this->key('invoices', 'customer_id', 'customers')],
                'customers' => [],
            ]
        );

        $this->assertSame(['invoices', 'payments'], $keys->using('customers', ['id']));
    }

    /**
     * An adapter that gives the type, the tables, and the keys of each table.
     *
     * @param list<string>                   $tables
     * @param array<string, list<Reference>> $keys
     */
    private function adapter(string $type, array $tables, array $keys): AdapterInterface
    {
        $adapter = $this->createStub(AdapterInterface::class);

        $adapter->method('getType')->willReturn($type);
        $adapter->method('listTables')->willReturn($tables);
        $adapter->method('describeReferences')->willReturnCallback(
            static fn (string $table): array => $keys[$table] ?? []
        );

        return $adapter;
    }

    /**
     * A key with one column that points to the id of a table.
     */
    private function key(string $table, string $column, string $referenced): Reference
    {
        return new Reference(
            $table . '_' . $column,
            ['referencedTable' => $referenced, 'columns' => [$column], 'referencedColumns' => ['id']]
        );
    }

    /**
     * customers; invoices -> customers; payments -> invoices; notes -> notes;
     * orders -> customers, two times.
     */
    private function map(): ForeignKeys
    {
        return new ForeignKeys(
            [
                'customers' => [],
                'invoices'  => [$this->key('invoices', 'customer_id', 'customers')],
                'payments'  => [$this->key('payments', 'invoice_id', 'invoices')],
                'notes'     => [$this->key('notes', 'parent_id', 'notes')],
                'orders'    => [
                    $this->key('orders', 'customer_id', 'customers'),
                    $this->key('orders', 'billing_id', 'customers'),
                ],
            ]
        );
    }

    /**
     * An adapter of PostgreSQL that gives the tables of each schema, and the
     * keys of each name (<schema>.<table>, or the table for the first
     * schema).
     *
     * @param array<string, list<string>>       $tables
     * @param array<array-key, list<Reference>> $keys   PHP makes a numeric name an integer key
     */
    private function schemaAdapter(array $tables, array $keys): AdapterInterface
    {
        $first   = (string) array_key_first($tables);
        $adapter = $this->createStub(AdapterInterface::class);

        $adapter->method('getType')->willReturn('pgsql');
        $adapter->method('listTables')->willReturnCallback(
            static fn (?string $schema = null): array => $tables[(string) $schema] ?? []
        );
        $adapter->method('listViews')->willReturn([]);
        $adapter->method('describeReferences')->willReturnCallback(
            static fn (string $table, ?string $schema = null): array => $keys[
                $first === $schema ? $table : $schema . '.' . $table
            ] ?? []
        );

        return $adapter;
    }

    /**
     * A key with one column that points to the id of a table of a schema.
     */
    private function schemaKey(string $table, string $column, string $schema, string $referenced): Reference
    {
        return new Reference(
            $table . '_' . $column,
            [
                'schema'            => 'crest',
                'referencedSchema'  => $schema,
                'referencedTable'   => $referenced,
                'columns'           => [$column],
                'referencedColumns' => ['id'],
                'onDelete'          => 'CASCADE',
                'onUpdate'          => 'RESTRICT',
            ]
        );
    }
}
