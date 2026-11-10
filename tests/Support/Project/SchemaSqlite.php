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

namespace Crest\Tests\Support\Project;

use Crest\Tests\Support\Migrations\BillingMigration;
use Phalcon\Db\Adapter\Pdo\Sqlite;

use function array_filter;
use function array_values;
use function str_starts_with;
use function strtolower;

/**
 * An SQLite adapter that acts as a server with one schema,
 * BillingMigration::SCHEMA, on the SQLite file of the suite (#69). A read for
 * that schema reads the file. A read for another schema, or for no schema,
 * finds nothing. Its type is mysql, so crest does not stop on the schema.
 *
 * Infection runs only the unit and the SQLite tests, so these tests hold the
 * schema code of make:model there. The server tests show the real behavior.
 */
final class SchemaSqlite extends Sqlite
{
    public function describeColumns(string $table, ?string $schema = null): array
    {
        return BillingMigration::SCHEMA === $schema ? parent::describeColumns($table) : [];
    }

    public function describeReferences(string $table, ?string $schema = null): array
    {
        return BillingMigration::SCHEMA === $schema ? parent::describeReferences($table) : [];
    }

    public function getType(): string
    {
        return 'mysql';
    }

    /**
     * The tables of the file, with no internal table of SQLite: a server has
     * none.
     */
    public function listTables(?string $schemaName = null): array
    {
        if (BillingMigration::SCHEMA !== $schemaName) {
            return [];
        }

        return array_values(array_filter(
            parent::listTables(),
            static fn (string $table): bool => false === str_starts_with(strtolower($table), 'sqlite_')
        ));
    }

    public function listViews(?string $schemaName = null): array
    {
        return BillingMigration::SCHEMA === $schemaName ? parent::listViews() : [];
    }

    public function tableExists(string $tableName, ?string $schemaName = null): bool
    {
        return BillingMigration::SCHEMA === $schemaName && parent::tableExists($tableName);
    }
}
