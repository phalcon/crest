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
use Crest\Tests\Support\Migrations\LinesMigration;
use Crest\Tests\Support\Migrations\ShipmentsMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;

use function file_get_contents;

/**
 * make:model with a composite key on MySQL, MariaDB and PostgreSQL (spec
 * 4.4). ModelCommandSqliteTest has the SQLite form.
 *
 * @group mysql
 * @group pgsql
 */
final class ModelCommandCompositeKeyTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model');
        $this->front(DatabaseFront::class);
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testACompositeKeyGivesArrays(): void
    {
        $this->skipCompositeKeyOnOldCphalcon();

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
}
