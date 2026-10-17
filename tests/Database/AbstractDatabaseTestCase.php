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

use Crest\Tests\Support\TestDatabase;
use PDO;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Talon\Database\Dialect;
use Phalcon\Talon\Database\Schema\AbstractSchema;
use Phalcon\Talon\Traits\DatabaseTrait;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function is_string;
use function phpversion;
use function version_compare;

/**
 * The base of the database tests. A test runs on the database of its suite,
 * and has only its own tables: the base removes each table of that database
 * before and after each test. So the DATA_* values must name a database for
 * the tests only.
 */
abstract class AbstractDatabaseTestCase extends TestCase
{
    use DatabaseTrait;

    private ?AdapterInterface $adapter = null;

    protected function setUp(): void
    {
        $this->dropAllTables();
    }

    protected function tearDown(): void
    {
        // The adapter goes with the test, so its connection closes.
        $this->adapter = null;

        $this->dropAllTables();
        self::resetConnections();
    }

    /**
     * The Phalcon adapter for the database of the suite, one for each test.
     */
    protected function adapter(): AdapterInterface
    {
        return $this->adapter ??= TestDatabase::adapter();
    }

    /**
     * Creates the table of each migration, in the order of the list. A class
     * name gives a migration with no values from the test.
     *
     * @param AbstractSchema|class-string<AbstractSchema> ...$migrations
     */
    protected function createTables(AbstractSchema | string ...$migrations): void
    {
        foreach ($migrations as $migration) {
            if (is_string($migration)) {
                $migration = $this->migration($migration);
            }

            $migration->create();
        }
    }

    /**
     * A migration on the connection of the test. The table does not exist
     * yet, so the migration does not empty it (withClear: false).
     *
     * @template T of AbstractSchema
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function migration(string $class): AbstractSchema
    {
        return new $class($this->getConnection()->getPdo(), false);
    }

    /**
     * Skips a composite key test on PostgreSQL with cphalcon before 5.24.0.
     * Its describeReferences() gives each column of the key with each
     * referenced column. The v6 package has the fix. The test runs again when
     * the CI gets the release with the fix.
     */
    protected function skipCompositeKeyOnOldCphalcon(): void
    {
        if (
            Dialect::Pgsql === $this->getDialect()
            && extension_loaded('phalcon')
            && version_compare((string) phpversion('phalcon'), '5.24.0', '<')
        ) {
            $this->markTestSkipped(
                'cphalcon before 5.24.0 gives the wrong columns for a composite key on PostgreSQL.'
            );
        }
    }

    /**
     * Removes each table of the database. MySQL and MariaDB do not check the
     * keys for it, and PostgreSQL removes the keys of the other tables
     * (CASCADE). SQLite does not check the keys.
     */
    private function dropAllTables(): void
    {
        $pdo       = $this->getConnection()->getPdo();
        $dialect   = $this->getDialect();
        $statement = $pdo->query(match ($dialect) {
            Dialect::Mysql  => 'SELECT table_name FROM information_schema.tables '
                . "WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name",
            Dialect::Pgsql  => 'SELECT tablename FROM pg_tables WHERE schemaname = current_schema() '
                . 'ORDER BY tablename',
            Dialect::Sqlite => "SELECT name FROM sqlite_master WHERE type = 'table' "
                . "AND name NOT LIKE 'sqlite_%' ORDER BY name",
        });

        /** @var list<string> $tables */
        $tables = false === $statement ? [] : $statement->fetchAll(PDO::FETCH_COLUMN);

        if (Dialect::Mysql === $dialect) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach ($tables as $table) {
            $pdo->exec(
                'DROP TABLE ' . $dialect->quoteIdentifier($table) . (Dialect::Pgsql === $dialect ? ' CASCADE' : '')
            );
        }

        if (Dialect::Mysql === $dialect) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
