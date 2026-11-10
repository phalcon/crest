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

use Crest\Generator\ForeignKeys;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\BillingCustomersMigration;
use Crest\Tests\Support\Migrations\BillingInvoicesMigration;
use Crest\Tests\Support\Migrations\BillingMigration;
use Crest\Tests\Support\Migrations\BillingPaymentsMigration;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\InvoicesMigration;
use Crest\Tests\Support\Migrations\ReceiptsToBillingMigration;

use function array_values;

/**
 * ForeignKeys::read() with a second schema (#69, spec 3.6), on the servers:
 * a key whose referenced table is not a table of the run goes to
 * elsewhere(). SQLite has no schemas.
 *
 * @group mysql
 * @group pgsql
 */
final class ForeignKeysSchemaTest extends AbstractDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function testAKeyFromTheDefaultSchemaIsElsewhere(): void
    {
        $this->createTables(BillingCustomersMigration::class, ReceiptsToBillingMigration::class);

        $keys = ForeignKeys::read($this->adapter());

        $this->assertSame([], $keys->of('receipts'));
        $this->assertCount(1, $keys->elsewhere('receipts'));
        $this->assertSame('customers', $keys->elsewhere('receipts')[0]->getReferencedTable());
    }

    public function testAKeyToTheDefaultSchemaIsElsewhere(): void
    {
        $this->createTables(CustomersMigration::class, BillingPaymentsMigration::class);

        $keys = ForeignKeys::read($this->adapter(), '', BillingMigration::SCHEMA);

        $this->assertSame([], $keys->of('payments'));
        $this->assertCount(1, $keys->elsewhere('payments'));
        $this->assertSame('customers', $keys->elsewhere('payments')[0]->getReferencedTable());
    }

    public function testATableOfTheSameNameInTheSchemaKeepsTheKey(): void
    {
        // The limit of spec 3.6: the key points to the customers of the
        // default schema, but the schema of the run has a table customers
        // too. The rule sees a table of the run, so the key stays.
        $this->createTables(
            CustomersMigration::class,
            BillingCustomersMigration::class,
            BillingPaymentsMigration::class
        );

        $keys = ForeignKeys::read($this->adapter(), '', BillingMigration::SCHEMA);

        $this->assertCount(1, $keys->of('payments'));
        $this->assertSame('customers', $keys->of('payments')[0]->getReferencedTable());
        $this->assertSame([], $keys->elsewhere('payments'));
    }

    public function testReadGivesTheKeysOfTheSchema(): void
    {
        $this->createTables(BillingCustomersMigration::class, BillingInvoicesMigration::class);

        $adapter = $this->adapter();
        $keys    = ForeignKeys::read($adapter, '', BillingMigration::SCHEMA);

        $this->assertSame(array_values($adapter->listTables(BillingMigration::SCHEMA)), $keys->tables());
        $this->assertCount(1, $keys->of('invoices'));
        $this->assertSame('customers', $keys->of('invoices')[0]->getReferencedTable());
        $this->assertSame([], $keys->elsewhere('invoices'));
    }

    public function testTheSameKeyInTwoSchemasGivesOneKeyInEach(): void
    {
        // The two keys have the same name in the two schemas. cphalcon before
        // 5.24.0 gives one key with the columns of both on PostgreSQL.
        $this->skipCompositeKeyOnOldCphalcon();

        $this->createTables(
            CustomersMigration::class,
            InvoicesMigration::class,
            BillingCustomersMigration::class,
            BillingInvoicesMigration::class
        );

        foreach ([null, BillingMigration::SCHEMA] as $schema) {
            $keys = ForeignKeys::read($this->adapter(), '', $schema);

            $this->assertCount(1, $keys->of('invoices'));
            $this->assertSame(['customer_id'], $keys->of('invoices')[0]->getColumns());
            $this->assertSame(['id'], $keys->of('invoices')[0]->getReferencedColumns());
            $this->assertSame('customers', $keys->of('invoices')[0]->getReferencedTable());
        }
    }
}
