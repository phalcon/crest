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

namespace Crest\Generator;

use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\ReferenceInterface;

use function array_keys;
use function array_map;
use function array_values;

/**
 * The foreign keys of the database, for make:model (#52). crest reads them
 * one time: listTables(), and describeReferences() for each table.
 * ModelRelations finds the keys of a table, and the keys that point to it,
 * in this map. So make:model --all does not read each table again for each
 * model.
 *
 * The map has the tables of listTables(): on MySQL and PostgreSQL also the
 * views, on SQLite also sqlite_sequence. They have no keys.
 */
final class ForeignKeys
{
    /**
     * @param array<array-key, list<ReferenceInterface>> $keys The keys of each table, in the order of
     *                                                         listTables(). PHP makes a numeric name an
     *                                                         integer key.
     */
    public function __construct(private readonly array $keys)
    {
    }

    /**
     * listTables() one time, and describeReferences() one time for each table.
     *
     * MySQL with lower_case_table_names 1 or 2 finds a table without regard
     * to case, but listTables() gives the stored name. So a table name that
     * the user typed, and that listTables() does not give, gets its keys from
     * the server, as before #52.
     *
     * @param string $table The table name that the user typed, or '' for --all
     */
    public static function read(AdapterInterface $adapter, string $table = ''): self
    {
        $keys = [];

        foreach ($adapter->listTables() as $listed) {
            $keys[$listed] = array_values($adapter->describeReferences($listed));
        }

        if ('' !== $table && false === isset($keys[$table])) {
            $keys[$table] = array_values($adapter->describeReferences($table));
        }

        return new self($keys);
    }

    /**
     * The keys of the table. A table that the map does not have has no keys.
     *
     * @return list<ReferenceInterface>
     */
    public function of(string $table): array
    {
        return $this->keys[$table] ?? [];
    }

    /**
     * The tables, in the order of listTables(), as strings.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return array_map(static fn (int | string $table): string => (string) $table, array_keys($this->keys));
    }
}
