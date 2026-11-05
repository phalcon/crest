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
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Db\Adapter\Pdo\Postgresql;

/**
 * `crest new --database` with MySQL, MariaDB and PostgreSQL (#54, spec
 * 4.2). The DbProvider of the project connects to the database of the suite
 * with the DB_* values that docker compose gives to the app container. The
 * values come from the DATA_* values of the suite. The database choice is
 * the driver of the suite: mysql, mariadb or pgsql.
 *
 * @group mysql
 * @group pgsql
 */
final class NewCommandServerTest extends AbstractNewCommandTestCase
{
    private const ADR = 'Generated\\Server\\Adr';

    private const MVC = 'Generated\\Server\\Mvc';

    public function testAGetenvValueWinsOverEnv(): void
    {
        // $_ENV names a host that does not exist: if it won, the connection
        // would fail.
        $project = $this->generate('mvc', $this->getDriver(), self::MVC);

        $this->databaseValues(true, false);
        $this->environment('DB_HOST', 'no-such-host', false, true);
        $this->createTables(CustomersMigration::class);

        $this->assertTrue($this->mvcDatabase($project)->tableExists('customers'));
    }

    public function testAnAdrProjectRegistersDbInItsContainer(): void
    {
        $project = $this->generate('adr', $this->getDriver(), self::ADR);

        $this->databaseValues(true, false);
        $this->createTables(CustomersMigration::class);

        $this->assertTrue($this->adrDatabase($project)->tableExists('customers'));
    }

    public function testAValueOnlyInEnvConnects(): void
    {
        // A loader such as phpdotenv fills $_ENV, and does not call putenv().
        $project = $this->generate('mvc', $this->getDriver(), self::MVC);

        $this->databaseValues(false, true);
        $this->createTables(CustomersMigration::class);

        $this->assertTrue($this->mvcDatabase($project)->tableExists('customers'));
    }

    public function testMakeModelReadsATableOfTheProject(): void
    {
        // The "Done when" of #54: after new, make:model reads the database
        // with no manual step.
        $project = $this->generate('mvc', $this->getDriver(), self::MVC);

        $this->databaseValues(true, false);
        $this->createTables(CustomersMigration::class);

        $this->assertInstanceOf(
            'pgsql' === $this->getDriver() ? Postgresql::class : Mysql::class,
            $this->mvcDatabase($project)
        );

        $this->makeModel($project, 'customers', 'CustomersBase');
    }
}
