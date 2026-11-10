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

use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\InvoicesMigration;
use Crest\Tests\Support\Migrations\LinesMigration;
use Crest\Tests\Support\Migrations\OddMigration;
use Crest\Tests\Support\Migrations\ShipmentsMigration;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Project\CompositeKeyFront;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\Project\NoKeyReadFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;

use function file_get_contents;

/**
 * make:model on SQLite with a test adapter. SQLite gives one reference for
 * each column of a composite key, so CompositeKeyFront gives the key as one
 * reference, as MySQL does (spec 4.3). NoKeyReadFront stops when crest reads
 * the keys, so it shows that make:model <table> checks the table first (#52).
 *
 * @group sqlite
 */
final class ModelCommandSqliteTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    private const MODELS = "['schema' => 'crest_billing']";

    private const NO_SCHEMAS = "crest.php: models.schema is 'crest_billing', but SQLite has no schemas; remove the key";

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model');
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testAColumnThatIsNotAPropertyNameStopsBeforeTheKeys(): void
    {
        $this->front(NoKeyReadFront::class);
        $this->createTables(OddMigration::class);

        $this->assertStops(
            ['odd'],
            "column 'first name' of table 'odd' is not a usable property name; "
            . 'give it one with --property="first name=<name>"'
        );
    }

    public function testACompositeKeyGivesArrays(): void
    {
        // CompositeKeyFront gives the key of shipments as one reference with
        // two columns, as MySQL does.
        $this->front(CompositeKeyFront::class);
        $this->createTables(LinesMigration::class, ShipmentsMigration::class);

        $status = $this->runCommand(['shipments']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "        \$this->belongsTo(\n"
            . "            ['order_id', 'line_no'],\n"
            . "            \\App\\Models\\Lines::class,\n"
            . "            ['order_id', 'number'],\n"
            . "            ['alias' => 'Lines']\n"
            . "        );\n",
            (string) file_get_contents($this->root . '/src/Models/Base/ShipmentsBase.php')
        );
    }

    public function testAKeyToATableThatDoesNotExistGivesARelation(): void
    {
        // SQLite has no schemas, so crest does not split the keys (#69, spec
        // 3.6): a key to a table that does not exist still gives a relation.
        $this->front(DatabaseFront::class);
        $this->createTables(InvoicesMigration::class);

        $status = $this->runCommand(['invoices']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "        \$this->belongsTo(\n"
            . "            'customer_id',\n"
            . "            \\App\\Models\\Customers::class,\n",
            (string) file_get_contents($this->root . '/src/Models/Base/InvoicesBase.php')
        );
    }

    public function testAMissingTableStopsBeforeTheKeys(): void
    {
        $this->front(NoKeyReadFront::class);

        $this->assertStops(['nothing'], "table 'nothing' was not found in the database of the 'db' service");
    }

    public function testASchemaStopsAllOnSqlite(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(UsersMigration::class);

        $this->assertStops(['--all'], self::NO_SCHEMAS);
    }

    public function testASchemaStopsOnSqlite(): void
    {
        $this->front(DatabaseFront::class, self::MODELS);
        $this->createTables(UsersMigration::class);

        $this->assertStops(['users'], self::NO_SCHEMAS);
    }
}
