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

use Crest\Console\Exceptions\Exception;
use Crest\Generator\TableColumns;
use Phalcon\DataMapper\Pdo\Connection;
use PHPUnit\Framework\TestCase;

/**
 * The columns of a table through a DataMapper connection (#84), on SQLite in
 * memory. The database suites run the queries of MySQL, MariaDB and
 * PostgreSQL.
 */
final class TableColumnsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');

        $this->connection->exec('CREATE TABLE users (name TEXT, id INTEGER PRIMARY KEY, email TEXT)');
        $this->connection->exec('CREATE TABLE pairs (a INTEGER, note TEXT, b INTEGER, PRIMARY KEY (b, a))');
        $this->connection->exec('CREATE TABLE notes (body TEXT)');
    }

    /**
     * The driver, and a part of the query that reads its catalog. A server
     * gives pk as a string or an integer.
     *
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function servers(): iterable
    {
        yield 'mysql and mariadb' => ['mysql', 'TABLE_SCHEMA = DATABASE()', '1'];
        yield 'pgsql' => ['pgsql', 'c.table_schema = current_schema()', 1];
    }

    /**
     * @dataProvider servers
     */
    public function testAServerIsReadThroughItsCatalog(string $driver, string $catalog, int | string $pk): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverName')->willReturn($driver);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains($catalog), ['table' => 'users'])
            ->willReturn([['name' => 'id', 'pk' => $pk], ['name' => 'name', 'pk' => '0']]);

        $columns = TableColumns::of($connection, 'users');

        $this->assertSame(['id', 'name'], $columns->columns);
        $this->assertSame(['id'], $columns->primaryKey);
    }

    public function testATableThatIsNotThereStops(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("table 'nothing' was not found in the database of the 'db' service");

        TableColumns::of($this->connection, 'nothing');
    }

    public function testATableWithNoPrimaryKeyHasNoKey(): void
    {
        $this->assertSame([], TableColumns::of($this->connection, 'notes')->primaryKey);
    }

    public function testEachColumnOfACompositeKeyIsInTheKey(): void
    {
        $columns = TableColumns::of($this->connection, 'pairs');

        $this->assertSame(['a', 'note', 'b'], $columns->columns);
        $this->assertSame(['a', 'b'], $columns->primaryKey);
    }

    public function testTheColumnsKeepTheOrderOfTheTable(): void
    {
        $columns = TableColumns::of($this->connection, 'users');

        $this->assertSame(['name', 'id', 'email'], $columns->columns);
        $this->assertSame(['id'], $columns->primaryKey);
    }
}
