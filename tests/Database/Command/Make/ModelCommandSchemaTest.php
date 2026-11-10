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

namespace Crest\Tests\Database\Command\Make;

use Crest\Project\Config;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\BillingBillsMigration;
use Crest\Tests\Support\Migrations\BillingCustomersMigration;
use Crest\Tests\Support\Migrations\BillingMigration;
use Crest\Tests\Support\Migrations\BillingPaymentsMigration;
use Crest\Tests\Support\Migrations\BillingUsersMigration;
use Crest\Tests\Support\Migrations\BillingVendorNamesViewMigration;
use Crest\Tests\Support\Migrations\BillingVendorsMigration;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\ReceiptsToBillingMigration;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Migrations\VendorsMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Talon\Database\Dialect;

use function file_get_contents;
use function file_put_contents;
use function str_replace;

use const PHP_EOL;

/**
 * make:model with a second schema (#69), on the servers. SQLite has no
 * schemas. Each test starts with the empty schema BillingMigration::SCHEMA.
 *
 * @group mysql
 * @group pgsql
 */
final class ModelCommandSchemaTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    private const MODELS = "['schema' => '" . BillingMigration::SCHEMA . "']";

    private const NOPE = "['schema' => 'crest_nope']";

    private const RECEIPTS_KEY = 'belongsTo receipts(customer_id) -> customers(id)';

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model-schema');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testAKeyToTheDefaultSchemaIsSkipped(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(CustomersMigration::class, BillingPaymentsMigration::class);

        $status = $this->runCommand(['payments']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Created ' . $this->root . '/src/Models/Base/PaymentsBase.php' . PHP_EOL
            . 'Skipped the relation belongsTo payments(customer_id) -> customers(id): '
            . 'the table customers is in another schema' . PHP_EOL
            . 'Created ' . $this->root . '/src/Models/Payments.php' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAllWritesOnlyTheTablesOfTheSchema(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(
            UsersMigration::class,
            BillingVendorsMigration::class,
            BillingBillsMigration::class,
            BillingVendorNamesViewMigration::class
        );

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Models/Vendors.php');
        $this->assertFileExists($this->root . '/src/Models/Base/VendorsBase.php');
        $this->assertFileExists($this->root . '/src/Models/Bills.php');
        $this->assertFileExists($this->root . '/src/Models/Base/BillsBase.php');
        $this->assertFileDoesNotExist($this->root . '/src/Models/Users.php');
        $this->assertFileDoesNotExist($this->root . '/src/Models/VendorNames.php');
        $this->assertStringEndsWith('2 tables: 2 models created, 0 kept, 0 failed.' . PHP_EOL, $this->readStdout());
    }

    public function testAMissingSchemaHasNoTablesToWriteOnPostgresql(): void
    {
        // PostgreSQL gives no tables for a schema that does not exist.
        if (Dialect::Pgsql !== $this->getDialect()) {
            $this->markTestSkipped('PostgreSQL gives no tables for a schema that does not exist.');
        }

        $this->front(DatabaseFront::class, self::NOPE);

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertSame("No tables to write in the schema 'crest_nope'." . PHP_EOL, $this->readStdout());
    }

    public function testAMissingSchemaStopsOnMysql(): void
    {
        // MySQL and MariaDB throw for a schema that does not exist.
        if (Dialect::Mysql !== $this->getDialect()) {
            $this->markTestSkipped('MySQL and MariaDB throw for a schema that does not exist.');
        }

        $this->front(DatabaseFront::class, self::NOPE);

        $status = $this->runCommand(['--all']);
        $stderr = $this->readStderr();

        $this->assertSame(1, $status);
        $this->assertStringStartsWith("crest: the schema 'crest_nope' cannot be read: SQLSTATE[", $stderr);
        $this->assertStringContainsString('1049', $stderr);
    }

    public function testAnEmptySchemaHasNoTablesToWrite(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(UsersMigration::class);

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertSame(
            "No tables to write in the schema '" . BillingMigration::SCHEMA . "'." . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAnExcludedTableOfTheSchemaKeepsItsRelation(): void
    {
        // --exclude skips the model, but the table is still a table of the
        // schema, so the key of bills to it gives a relation.
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(BillingVendorsMigration::class, BillingBillsMigration::class);

        $status = $this->runCommand(['--all', '--exclude=vendors']);

        $this->assertSame(0, $status);
        $this->assertFileDoesNotExist($this->root . '/src/Models/Vendors.php');
        $this->assertStringContainsString(
            "            \\App\\Models\\Vendors::class,\n",
            (string) file_get_contents($this->root . '/src/Models/Base/BillsBase.php')
        );
    }

    public function testAnExcludeNameOnlyInTheDefaultSchemaStops(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(UsersMigration::class, BillingVendorsMigration::class);

        $this->assertStops(['--all', '--exclude=users'], '--exclude: the database has no table users');
    }

    public function testARelationInTheSchemaGivesBelongsToAndHasMany(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(BillingVendorsMigration::class, BillingBillsMigration::class);

        $this->assertSame(0, $this->runCommand(['bills']));
        $this->assertSame(0, $this->runCommand(['vendors']));

        $this->assertStringContainsString(
            "        \$this->belongsTo(\n"
            . "            'vendor_id',\n"
            . "            \\App\\Models\\Vendors::class,\n"
            . "            'id',\n"
            . "            ['alias' => 'Vendor']\n"
            . "        );\n",
            (string) file_get_contents($this->root . '/src/Models/Base/BillsBase.php')
        );
        $this->assertStringContainsString(
            "        \$this->hasMany(\n"
            . "            'id',\n"
            . "            \\App\\Models\\Bills::class,\n"
            . "            'vendor_id',\n"
            . "            ['alias' => 'Bills']\n"
            . "        );\n",
            (string) file_get_contents($this->root . '/src/Models/Base/VendorsBase.php')
        );
    }

    public function testATableOnlyInTheDefaultSchemaStops(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(UsersMigration::class);

        $this->assertStops(
            ['users'],
            "table 'users' was not found in the schema '" . BillingMigration::SCHEMA . "' of the 'db' service"
        );
    }

    public function testTheBaseSetsTheSchemaAfterTheSource(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(BillingVendorsMigration::class);

        $status = $this->runCommand(['vendors']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "        \$this->setSource('vendors');\n"
            . "        \$this->setSchema('" . BillingMigration::SCHEMA . "');\n"
            . "    }\n",
            (string) file_get_contents($this->root . '/src/Models/Base/VendorsBase.php')
        );
    }

    public function testTheColumnsComeFromTheSchema(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(UsersMigration::class, BillingUsersMigration::class);

        $status = $this->runCommand(['users']);
        $base   = (string) file_get_contents($this->root . '/src/Models/Base/UsersBase.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString("    public ?string \$email = null;\n", $base);
        $this->assertStringNotContainsString('$name', $base);
    }

    public function testTheModelsReadARowAndItsRelatedRows(): void
    {
        // The "done when" of #69. The classes stay loaded in the PHP process,
        // so no other test may use the tables vendors and bills. The vendors
        // of the default schema stay empty: a model with no setSchema() reads
        // them, and finds no row.
        $vendors = $this->migration(BillingVendorsMigration::class);
        $bills   = $this->migration(BillingBillsMigration::class);

        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(VendorsMigration::class, $vendors, $bills);
        $vendors->insert('acme');
        $bills->insert('march', 1);

        $this->assertSame(0, $this->runCommand(['vendors']));
        $this->assertSame(0, $this->runCommand(['bills']));

        require $this->root . '/src/Models/Base/VendorsBase.php';
        require $this->root . '/src/Models/Vendors.php';
        require $this->root . '/src/Models/Base/BillsBase.php';
        require $this->root . '/src/Models/Bills.php';

        // The models get their services from the default DI. An earlier test
        // can have made the default DI, so the test sets the DI of the front.
        Di::setDefault((new DatabaseFront())->boot());

        /** @var class-string<Model> $class */
        $class = Config::discover($this->root)->namespaceFor('model') . '\\Bills';
        $bill  = $class::findFirst();

        $this->assertInstanceOf(Model::class, $bill);
        $this->assertSame(['id' => 1, 'title' => 'march', 'vendor_id' => 1], $bill->toArray());

        // getRelated() finds the relation by its alias, as $bill->Vendor does.
        $vendor = $bill->getRelated('Vendor');

        $this->assertInstanceOf(Model::class, $vendor);
        $this->assertSame(['id' => 1, 'name' => 'acme'], $vendor->toArray());

        $related = $vendor->getRelated('Bills');

        $this->assertInstanceOf(ResultsetInterface::class, $related);
        $this->assertSame([['id' => 1, 'title' => 'march', 'vendor_id' => 1]], $related->toArray());
    }

    public function testWithNoSchemaAKeyToAnotherSchemaIsSkipped(): void
    {
        // A project with no models.schema, and a key to a table of another
        // schema: the next run removes the relation, and says so (spec 3.6).
        $this->front(DatabaseFront::class);
        $this->createTables(BillingCustomersMigration::class, ReceiptsToBillingMigration::class);

        $status = $this->runCommand(['receipts']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Created ' . $this->root . '/src/Models/Base/ReceiptsBase.php' . PHP_EOL
            . 'Skipped the relation ' . self::RECEIPTS_KEY . ': the table customers is in another schema' . PHP_EOL
            . 'Created ' . $this->root . '/src/Models/Receipts.php' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringNotContainsString(
            'belongsTo',
            (string) file_get_contents($this->root . '/src/Models/Base/ReceiptsBase.php')
        );
    }

    public function testWithNoSchemaAnAliasOfAKeyToAnotherSchemaIsDropped(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(BillingCustomersMigration::class, ReceiptsToBillingMigration::class);
        $this->runCommand(['receipts']);

        $baseFile = $this->root . '/src/Models/Base/ReceiptsBase.php';

        file_put_contents(
            $baseFile,
            str_replace(
                'abstract class ReceiptsBase',
                "#[\\Crest\\Attribute\\RelationAlias('" . self::RECEIPTS_KEY . "', alias: 'Buyer')]\n"
                . 'abstract class ReceiptsBase',
                (string) file_get_contents($baseFile)
            )
        );

        $this->freshStreams();
        $this->runCommand(['receipts']);

        $this->assertStringContainsString(
            "Dropped the alias Buyer of '" . self::RECEIPTS_KEY . "': crest skips the relation",
            $this->readStdout()
        );
    }
}
