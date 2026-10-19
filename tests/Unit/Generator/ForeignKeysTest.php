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
 * The key map of make:model (#52, spec 3.3), with no database.
 */
final class ForeignKeysTest extends TestCase
{
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
