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

use Crest\Command\Make\ScaffoldCommand;
use Crest\Project\Config;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\BillingBillsMigration;
use Crest\Tests\Support\Migrations\BillingCustomersMigration;
use Crest\Tests\Support\Migrations\BillingMigration;
use Crest\Tests\Support\Migrations\BillingPaymentsMigration;
use Crest\Tests\Support\Migrations\BillingUsersMigration;
use Crest\Tests\Support\Migrations\BillingVendorsMigration;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\ReceiptsToBillingMigration;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\ResultsetInterface;

use function file_get_contents;

use const PHP_EOL;

/**
 * make:model and make:scaffold with a list of schemas (#86), on the servers:
 * the default schema of the connection, then BillingMigration::SCHEMA. SQLite
 * has no schemas. Each test starts with the empty schema
 * BillingMigration::SCHEMA.
 *
 * @group mysql
 * @group pgsql
 */
final class ModelCommandSchemasTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startScratchProject('make-model-schemas');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();

        parent::tearDown();
    }

    public function testAKeyFromTheOtherSchemaGivesBelongsToAndHasMany(): void
    {
        $this->skipReferencedSchemaOnOldCphalcon();
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(CustomersMigration::class, BillingPaymentsMigration::class);

        $this->assertSame(0, $this->runCommand(['--all']));
        $this->assertStringContainsString(
            "        \$this->belongsTo(\n"
            . "            'customer_id',\n"
            . "            \\App\\Models\\Customers::class,\n"
            . "            'id',\n"
            . "            ['alias' => 'Customer']\n"
            . "        );\n",
            (string) file_get_contents($this->root . '/src/Models/Base/PaymentsBase.php')
        );
        $this->assertStringContainsString(
            "        \$this->hasMany(\n"
            . "            'id',\n"
            . "            \\App\\Models\\Payments::class,\n"
            . "            'customer_id',\n"
            . "            ['alias' => 'Payments']\n"
            . "        );\n",
            (string) file_get_contents($this->root . '/src/Models/Base/CustomersBase.php')
        );
    }

    public function testAKeyToTheOtherSchemaGivesARelation(): void
    {
        $this->skipReferencedSchemaOnOldCphalcon();
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(BillingCustomersMigration::class, ReceiptsToBillingMigration::class);

        $this->assertSame(0, $this->runCommand(['receipts']));
        $this->assertStringContainsString(
            "        \$this->belongsTo(\n"
            . "            'customer_id',\n"
            . "            \\App\\Models\\Customers::class,\n"
            . "            'id',\n"
            . "            ['alias' => 'Customer']\n"
            . "        );\n",
            (string) file_get_contents($this->root . '/src/Models/Base/ReceiptsBase.php')
        );
    }

    public function testAllWritesTheTablesOfEachSchema(): void
    {
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(UsersMigration::class, BillingVendorsMigration::class, BillingBillsMigration::class);

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Models/Users.php');
        $this->assertFileExists($this->root . '/src/Models/Vendors.php');
        $this->assertFileExists($this->root . '/src/Models/Bills.php');
        $this->assertStringContainsString(
            "        \$this->setSource('users');\n        \$this->setSchema('" . $this->homeSchema() . "');\n",
            (string) file_get_contents($this->root . '/src/Models/Base/UsersBase.php')
        );
        $this->assertStringContainsString(
            "        \$this->setSource('vendors');\n        \$this->setSchema('" . BillingMigration::SCHEMA . "');\n",
            (string) file_get_contents($this->root . '/src/Models/Base/VendorsBase.php')
        );
        $this->assertStringContainsString(
            'Table ' . BillingMigration::SCHEMA . '.vendors' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringEndsWith('3 tables: 3 models created, 0 kept, 0 failed.' . PHP_EOL, $this->readStdout());
    }

    public function testAScaffoldOfATableOfAnotherSchema(): void
    {
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(BillingVendorsMigration::class);

        $status = $this->runProjectCommand(
            'make:scaffold',
            ScaffoldCommand::class,
            [BillingMigration::SCHEMA . '.vendors']
        );

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Controllers/VendorsController.php');
        $this->assertStringContainsString(
            "        \$this->setSource('vendors');\n        \$this->setSchema('" . BillingMigration::SCHEMA . "');\n",
            (string) file_get_contents($this->root . '/src/Models/Base/VendorsBase.php')
        );
    }

    public function testATableOfTheOtherSchemaNeedsItsSchema(): void
    {
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(BillingVendorsMigration::class);

        $this->assertStops(
            ['vendors'],
            "table 'vendors' was not found in the schema '" . $this->homeSchema() . "' of the 'db' service; "
            . 'did you mean ' . BillingMigration::SCHEMA . '.vendors?'
        );

        $this->freshStreams();

        $this->assertSame(0, $this->runCommand([BillingMigration::SCHEMA . '.vendors']));

        $base = (string) file_get_contents($this->root . '/src/Models/Base/VendorsBase.php');

        $this->assertStringContainsString(' * run make:model ' . BillingMigration::SCHEMA . '.vendors. Put', $base);
        $this->assertStringContainsString("        \$this->setSource('vendors');\n", $base);
    }

    public function testOneNameInTwoSchemasStopsUntilItHasAClass(): void
    {
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(UsersMigration::class, BillingUsersMigration::class);

        $this->assertSame(1, $this->runCommand(['--all']));
        $this->assertFileExists($this->root . '/src/Models/Users.php');
        $this->assertStringContainsString(
            'Failed: the tables ' . BillingMigration::SCHEMA . '.users and users give the class Users; '
            . "add '" . BillingMigration::SCHEMA . ".users' to models.classes" . PHP_EOL,
            $this->readStdout()
        );

        $this->front(
            DatabaseFront::class,
            $this->models("'classes' => ['" . BillingMigration::SCHEMA . ".users' => 'BillingUser']")
        );
        $this->freshStreams();

        $this->assertSame(0, $this->runCommand(['--all']));
        $this->assertStringContainsString(
            "        \$this->setSource('users');\n        \$this->setSchema('" . BillingMigration::SCHEMA . "');\n",
            (string) file_get_contents($this->root . '/src/Models/Base/BillingUserBase.php')
        );
        $this->assertStringContainsString(
            "    public ?string \$name = null;\n",
            (string) file_get_contents($this->root . '/src/Models/Base/UsersBase.php')
        );
    }

    public function testOneTableOfTwoWithOneNameStopsBeforeItWrites(): void
    {
        // The class Users is the class of users, so a run for the other table
        // must not write over UsersBase.php.
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(UsersMigration::class, BillingUsersMigration::class);

        $this->assertStops(
            [BillingMigration::SCHEMA . '.users'],
            'the tables ' . BillingMigration::SCHEMA . '.users and users give the class Users; '
            . "add '" . BillingMigration::SCHEMA . ".users' to models.classes"
        );
    }

    public function testTheModelsReadARowOfEachSchema(): void
    {
        // The "done when" of #86. The classes stay loaded in the PHP process,
        // so no other test may require the models Customers and Payments.
        $this->skipReferencedSchemaOnOldCphalcon();
        $this->front(DatabaseFront::class, $this->models());
        $this->createTables(CustomersMigration::class, BillingPaymentsMigration::class);

        $pdo = $this->getConnection()->getPdo();
        $pdo->exec("INSERT INTO customers (name) VALUES ('acme')");
        $pdo->exec('INSERT INTO ' . BillingMigration::SCHEMA . '.payments (customer_id) VALUES (1)');

        $this->assertSame(0, $this->runCommand(['--all']));

        require $this->root . '/src/Models/Base/CustomersBase.php';
        require $this->root . '/src/Models/Customers.php';
        require $this->root . '/src/Models/Base/PaymentsBase.php';
        require $this->root . '/src/Models/Payments.php';

        // The models get their services from the default DI. An earlier test
        // can have made the default DI, so the test sets the DI of the front.
        Di::setDefault((new DatabaseFront())->boot());

        /** @var class-string<Model> $class */
        $class   = Config::discover($this->root)->namespaceFor('model') . '\\Payments';
        $payment = $class::findFirst();

        $this->assertInstanceOf(Model::class, $payment);
        $this->assertSame(['id' => 1, 'customer_id' => 1], $payment->toArray());

        $customer = $payment->getRelated('Customer');

        $this->assertInstanceOf(Model::class, $customer);
        $this->assertSame(['id' => 1, 'name' => 'acme'], $customer->toArray());

        $payments = $customer->getRelated('Payments');

        $this->assertInstanceOf(ResultsetInterface::class, $payments);
        $this->assertSame([['id' => 1, 'customer_id' => 1]], $payments->toArray());
    }

    /**
     * The models key: the default schema of the connection, then the second
     * schema, and the other keys of the test.
     */
    private function models(string $more = ''): string
    {
        return "['schema' => ['" . $this->homeSchema() . "', '" . BillingMigration::SCHEMA . "']"
            . ('' === $more ? '' : ', ' . $more)
            . ']';
    }
}
