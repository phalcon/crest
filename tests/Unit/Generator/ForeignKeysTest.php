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
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Reference;
use PHPUnit\Framework\TestCase;

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

        $this->assertSame([$key], ForeignKeys::read($adapter, '', 'billing')->of('bills'));
        $this->assertSame([$key], ForeignKeys::read($adapter, 'Bills', 'billing')->of('Bills'));
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
}
