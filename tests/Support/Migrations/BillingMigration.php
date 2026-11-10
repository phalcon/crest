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

namespace Crest\Tests\Support\Migrations;

use LogicException;
use Phalcon\Talon\Database\Dialect;
use Phalcon\Talon\Database\Schema\AbstractSchema;

use function is_string;

/**
 * A table that names the second schema of the tests (#69). The test creates
 * the schema first (AbstractDatabaseTestCase::createSchema()). SQLite has no
 * schemas, so these tables do not run on SQLite.
 *
 * Each key names the schema of its referenced table. A key to the default
 * schema uses home(), so the table does not depend on how the server finds
 * a table with no schema in its name.
 */
abstract class BillingMigration extends AbstractSchema
{
    public const SCHEMA = 'crest_billing';

    protected function getStatementsSqlite(): array
    {
        return [];
    }

    /**
     * The default schema of the connection: the database on MySQL, the
     * current schema on PostgreSQL.
     */
    protected function home(): string
    {
        $pdo = $this->connection ?? throw new LogicException('The migration has no connection');

        $statement = $pdo->query(
            Dialect::Pgsql === Dialect::fromPdo($pdo) ? 'SELECT current_schema()' : 'SELECT DATABASE()'
        );
        $home      = false === $statement ? false : $statement->fetchColumn();

        return is_string($home) ? $home : throw new LogicException('The connection has no default schema');
    }
}
