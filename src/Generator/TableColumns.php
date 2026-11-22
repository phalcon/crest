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

use Crest\Console\Exceptions\Exception;
use Phalcon\DataMapper\Pdo\Connection;

use function sprintf;

/**
 * The columns and the primary key of a table, for make:repository (#84). A
 * DataMapper connection has no describeColumns(), as Phalcon\Db has, so this
 * is one query for each driver. It reads only the names, and if each column
 * is in the primary key. When DataMapper gives this itself (an Info class),
 * its call replaces the queries.
 */
final class TableColumns
{
    private const MYSQL = "SELECT COLUMN_NAME AS name, CASE WHEN COLUMN_KEY = 'PRI' THEN 1 ELSE 0 END AS pk "
        . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table '
        . 'ORDER BY ORDINAL_POSITION';

    private const PGSQL = 'SELECT c.column_name AS name, CASE WHEN k.column_name IS NULL THEN 0 ELSE 1 END AS pk '
        . 'FROM information_schema.columns AS c '
        . 'LEFT JOIN information_schema.table_constraints AS t ON t.table_schema = c.table_schema '
        . "AND t.table_name = c.table_name AND t.constraint_type = 'PRIMARY KEY' "
        . 'LEFT JOIN information_schema.key_column_usage AS k ON k.constraint_name = t.constraint_name '
        . 'AND k.table_schema = t.table_schema AND k.table_name = t.table_name AND k.column_name = c.column_name '
        . 'WHERE c.table_schema = current_schema() AND c.table_name = :table '
        . 'ORDER BY c.ordinal_position';

    // pk is the position of the column in the primary key, 0 for no key.
    private const SQLITE = 'SELECT name, pk FROM pragma_table_info(:table) ORDER BY cid';

    /**
     * @param list<string> $columns    The columns, in the order of the table
     * @param list<string> $primaryKey The columns of the primary key
     */
    private function __construct(
        public readonly array $columns,
        public readonly array $primaryKey,
    ) {
    }

    /**
     * The columns of the table. A table with no columns does not exist.
     * DataMapper has the drivers mysql (also MariaDB), pgsql and sqlite.
     */
    public static function of(Connection $connection, string $table): self
    {
        $query = match ($connection->getDriverName()) {
            'pgsql'  => self::PGSQL,
            'sqlite' => self::SQLITE,
            default  => self::MYSQL,
        };

        /** @var list<array{name: string, pk: int|string}> $rows */
        $rows    = $connection->fetchAll($query, ['table' => $table]);
        $columns = [];
        $key     = [];

        foreach ($rows as $row) {
            $columns[] = $row['name'];

            if (0 < $row['pk']) {
                $key[] = $row['name'];
            }
        }

        if ([] === $columns) {
            throw new Exception(sprintf("table '%s' was not found in the database of the 'db' service", $table));
        }

        return new self($columns, $key);
    }
}
