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
}
