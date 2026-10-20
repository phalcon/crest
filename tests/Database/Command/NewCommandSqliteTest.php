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

namespace Crest\Tests\Database\Command;

use Crest\Tests\Support\Migrations\CustomersMigration;
use PDO;

/**
 * `crest new --database=sqlite` (#54, spec 4.2): the DbProvider of the
 * project opens a file of the project, relative to the project root. The
 * tests use the file that new writes, not the database of the suite: an
 * empty file is a valid SQLite database, and DbProvider puts the project
 * root before DB_NAME.
 *
 * @group sqlite
 */
final class NewCommandSqliteTest extends AbstractNewCommandTestCase
{
    private const ADR = 'Generated\\Sqlite\\Adr';

    private const MVC = 'Generated\\Sqlite\\Mvc';

    public function testAGetenvValueWinsOverEnv(): void
    {
        // $_ENV names a file in a folder that does not exist: if it won, the
        // connection would fail.
        $project = $this->generate('mvc', 'sqlite', self::MVC);

        $this->createCustomers($project . '/storage/other.sqlite');
        $this->environment('DB_NAME', 'storage/other.sqlite');
        $this->environment('DB_NAME', 'no-such-folder/database.sqlite', false, true);

        $this->assertTrue($this->mvcDatabase($project)->tableExists('customers'));
    }

    public function testAnAdrProjectRegistersDbInItsContainer(): void
    {
        $project = $this->generate('adr', 'sqlite', self::ADR);

        $this->createCustomers($project . '/storage/database.sqlite');

        $this->assertTrue($this->adrDatabase($project)->tableExists('customers'));
    }

    public function testAValueOnlyInEnvConnects(): void
    {
        // Without $_ENV, DbProvider opens the default file, which has no
        // table.
        $project = $this->generate('mvc', 'sqlite', self::MVC);

        $this->createCustomers($project . '/storage/other.sqlite');
        $this->environment('DB_NAME', 'storage/other.sqlite', false, true);

        $this->assertTrue($this->mvcDatabase($project)->tableExists('customers'));
    }

    public function testMakeModelReadsATableOfTheProjectFile(): void
    {
        // No DB_NAME: the default of DbProvider is the file that new writes.
        $project = $this->generate('mvc', 'sqlite', self::MVC);

        $this->createCustomers($project . '/storage/database.sqlite');

        $this->makeModel($project, 'customers', 'CustomersBase');
    }

    /**
     * Creates the table customers in a SQLite file. PDO creates the file when
     * it does not exist.
     */
    private function createCustomers(string $file): void
    {
        (new CustomersMigration(new PDO('sqlite:' . $file), false))->create();
    }
}
