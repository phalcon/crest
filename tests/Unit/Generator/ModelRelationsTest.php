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
use Crest\Generator\GeneratorTypes;
use Crest\Generator\ModelNames;
use Crest\Generator\ModelRelations;
use Phalcon\Db\Reference;
use PHPUnit\Framework\TestCase;

/**
 * The relations of a key to a table of another schema (#69, spec 3.6), with
 * no database. The tests in tests/Database show the relations of each
 * driver.
 *
 * @phpstan-import-type Stored from GeneratorTypes
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

    public function testAKeyToASchemaNotInTheListIsSkippedWithTheSchema(): void
    {
        $relations = ModelRelations::of(
            new ForeignKeys(
                ['receipts' => []],
                ['receipts' => [$this->reference(['customer_id'], 'crm.customers', ['id'])]]
            ),
            ModelNames::of(['schema' => ['public', 'billing']]),
            'receipts',
            'App\\Models',
            ['id', 'customer_id'],
            [],
            self::FILE
        );

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo receipts(customer_id) -> crm.customers(id): '
                . 'the schema crm is not in models.schema',
            ],
            $relations->skipped
        );
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

    public function testARelationToAnotherSchemaOfTheListIsWritten(): void
    {
        $relations = $this->listRelations(
            ['receipts' => [$this->reference(['customer_id'], 'billing.customers', ['id'])], 'billing.customers' => []],
            'receipts',
            ['id', 'customer_id']
        );

        $this->assertSame([], $relations->skipped);
        $this->assertCount(1, $relations->relations);
        $this->assertSame('\\App\\Models\\Customers', $relations->relations[0]['class']);
        $this->assertSame('Customer', $relations->relations[0]['alias']);
        $this->assertSame(
            'belongsTo receipts(customer_id) -> billing.customers(id)',
            $relations->relations[0]['key']
        );
    }

    public function testARelationToATableWithAClashingClassIsSkipped(): void
    {
        // The model of users is not the model of audit.users.
        $relations = ModelRelations::of(
            new ForeignKeys(
                [
                    'receipts'    => [$this->reference(['user_id'], 'audit.users', ['id'])],
                    'users'       => [],
                    'audit.users' => [],
                ]
            ),
            ModelNames::of(['schema' => ['public', 'audit']])->withTables(['receipts', 'users', 'audit.users']),
            'receipts',
            'App\\Models',
            ['id', 'user_id'],
            [],
            self::FILE
        );

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo receipts(user_id) -> audit.users(id): the tables audit.users and '
                . "users give the class Users; add 'audit.users' to models.classes",
            ],
            $relations->skipped
        );
    }

    public function testHasManyComesFromAKeyOfAnotherSchema(): void
    {
        $relations = $this->listRelations(
            ['customers' => [], 'billing.payments' => [$this->reference(['customer_id'], 'customers', ['id'])]],
            'customers',
            ['id']
        );

        $this->assertSame([], $relations->skipped);
        $this->assertCount(1, $relations->relations);
        $this->assertSame('has-many', $relations->relations[0]['kind']);
        $this->assertSame('\\App\\Models\\Payments', $relations->relations[0]['class']);
        $this->assertSame('Payments', $relations->relations[0]['alias']);
        $this->assertSame('hasMany billing.payments(customer_id) -> customers(id)', $relations->relations[0]['key']);
    }

    public function testTheAliasOfAKeyOfManyColumnsUsesTheTablePart(): void
    {
        $relations = $this->listRelations(
            [
                'receipts'          => [
                    $this->reference(['customer_id', 'region_id'], 'billing.customers', ['id', 'region_id']),
                ],
                'billing.customers' => [],
            ],
            'receipts',
            ['id', 'customer_id', 'region_id']
        );

        $this->assertSame([], $relations->skipped);
        $this->assertSame('Customers', $relations->relations[0]['alias']);
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
     * The relations of the table, with the keys of each name, and the
     * schemas public and billing.
     *
     * @param array<string, list<Reference>> $keys
     * @param list<string>                   $properties
     */
    private function listRelations(array $keys, string $table, array $properties): ModelRelations
    {
        return ModelRelations::of(
            new ForeignKeys($keys),
            ModelNames::of(['schema' => ['public', 'billing']]),
            $table,
            'App\\Models',
            $properties,
            [],
            self::FILE
        );
    }

    /**
     * A key with the columns to the referenced columns of the name.
     *
     * @param list<string> $columns
     * @param list<string> $referenced
     */
    private function reference(array $columns, string $table, array $referenced): Reference
    {
        return new Reference(
            'key_' . $columns[0],
            ['referencedTable' => $table, 'columns' => $columns, 'referencedColumns' => $referenced]
        );
    }

    /**
     * The relations of the table, with the keys of invoices to another
     * schema.
     *
     * @param list<Reference> $elsewhere
     * @param Stored          $stored
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
