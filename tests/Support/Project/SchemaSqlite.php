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
use function in_array;
use function str_starts_with;
use function strtolower;

/**
 * An SQLite adapter that acts as a server with one schema,
 * BillingMigration::SCHEMA, on the SQLite file of the suite (#69). A read for
 * that schema reads the file. A read for another schema, or for no schema,
 * finds nothing. Its type is mysql, so crest does not stop on the schema.
 * TwoSchemaSqlite adds a second schema with the same tables (#86).
 *
 * Infection runs only the unit and the SQLite tests, so these tests hold the
 * schema code of make:model there. The server tests show the real behavior.
 */
class SchemaSqlite extends Sqlite
{
    /**
     * The schemas whose reads read the file.
     *
     * @var list<string>
     */
    protected const SCHEMAS = [BillingMigration::SCHEMA];

    public function describeColumns(string $table, ?string $schema = null): array
    {
        return $this->has($schema) ? parent::describeColumns($table) : [];
    }

    public function describeReferences(string $table, ?string $schema = null): array
    {
        return $this->has($schema) ? parent::describeReferences($table) : [];
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
        if (false === $this->has($schemaName)) {
            return [];
        }

        return array_values(array_filter(
            parent::listTables(),
            static fn (string $table): bool => false === str_starts_with(strtolower($table), 'sqlite_')
        ));
    }

    public function listViews(?string $schemaName = null): array
    {
        return $this->has($schemaName) ? parent::listViews() : [];
    }

    public function tableExists(string $tableName, ?string $schemaName = null): bool
    {
        return $this->has($schemaName) && parent::tableExists($tableName);
    }

    private function has(?string $schema): bool
    {
        return in_array($schema, static::SCHEMAS, true);
    }
}
