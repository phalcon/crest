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
use Crest\Generator\ModelRelations;
use Phalcon\Db\Reference;
use PHPUnit\Framework\TestCase;

/**
 * The relations of a key to a table of another schema (#69, spec 3.6), with
 * no database. The tests in tests/Database show the relations of each
 * driver.
 */
final class ModelRelationsTest extends TestCase
{
    private const FILE = '/app/src/Models/Base/InvoicesBase.php';

    private const KEY = 'belongsTo invoices(customer_id) -> customers(id)';

    private const SKIPPED = 'Skipped the relation ' . self::KEY . ': the table customers is in another schema';

    public function testAKeyToAnotherSchemaGivesNoHasMany(): void
    {
        $relations = $this->relations('customers', [$this->key()]);

        $this->assertSame([], $relations->relations);
        $this->assertSame([], $relations->skipped);
    }

    public function testAKeyToAnotherSchemaIsSkipped(): void
    {
        $relations = $this->relations('invoices', [$this->key()]);

        $this->assertSame([], $relations->relations);
        $this->assertSame([self::SKIPPED], $relations->skipped);
        $this->assertSame([], $relations->dropped);
    }

    public function testAnAliasOfAKeyToAnotherSchemaIsDropped(): void
    {
        $relations = $this->relations('invoices', [$this->key()], [self::KEY => ['alias' => 'Buyer', 'line' => 12]]);

        $this->assertSame([], $relations->relations);
        $this->assertSame([self::SKIPPED], $relations->skipped);
        $this->assertSame(
            ["Dropped the alias Buyer of '" . self::KEY . "': crest skips the relation"],
            $relations->dropped
        );
    }

    public function testTwoIdenticalKeysToAnotherSchemaGiveOneLine(): void
    {
        $relations = $this->relations('invoices', [$this->key(), $this->key()]);

        $this->assertSame([self::SKIPPED], $relations->skipped);
    }

    /**
     * The key invoices(customer_id) -> customers(id).
     */
    private function key(): Reference
    {
        return new Reference(
            'invoices_customer',
            ['referencedTable' => 'customers', 'columns' => ['customer_id'], 'referencedColumns' => ['id']]
        );
    }

    /**
     * The relations of the table, with the keys of invoices to another
     * schema.
     *
     * @param list<Reference>                                $elsewhere
     * @param array<string, array{alias: string, line: int}> $stored
     */
    private function relations(string $table, array $elsewhere, array $stored = []): ModelRelations
    {
        return ModelRelations::of(
            new ForeignKeys(['customers' => [], 'invoices' => []], ['invoices' => $elsewhere]),
            ModelNames::of([]),
            $table,
            'App\\Models',
            ['id', 'customer_id'],
            $stored,
            self::FILE
        );
    }
}
