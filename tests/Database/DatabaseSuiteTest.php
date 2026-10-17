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

namespace Crest\Tests\Database;

use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\InvoicesMigration;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Db\Adapter\Pdo\Postgresql;
use Phalcon\Db\Adapter\Pdo\Sqlite;

/**
 * The setup of the database suites: the adapter of the suite, the tables of
 * the migrations, and the tables that the base removes before each test.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class DatabaseSuiteTest extends AbstractDatabaseTestCase
{
    public function testSetUpRemovesEachTableAlsoWithAKey(): void
    {
        // invoices has a key to customers, so MySQL and PostgreSQL cannot
        // remove customers first without more steps.
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->setUp();

        $this->assertFalse($this->adapter()->tableExists('customers'));
        $this->assertFalse($this->adapter()->tableExists('invoices'));
    }

    public function testTheAdapterIsForTheDriverOfTheSuite(): void
    {
        $expected = match ($this->getDriver()) {
            'mariadb', 'mysql' => Mysql::class,
            'pgsql'            => Postgresql::class,
            default            => Sqlite::class,
        };

        $this->assertInstanceOf($expected, $this->adapter());
    }

    public function testTheAdapterReadsTheTablesOfTheMigrations(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertTrue($this->adapter()->tableExists('customers'));
        $this->assertTrue($this->adapter()->tableExists('invoices'));
    }
}
