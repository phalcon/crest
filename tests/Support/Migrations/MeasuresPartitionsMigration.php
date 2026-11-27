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
use Phalcon\Talon\Database\Schema\AbstractSchema;

/**
 * The table measures, partitioned by month, with the partitions
 * measures_2026_01 and measures_2026_02. PostgreSQL only: its
 * information_schema.tables lists each partition as a table (#101).
 */
final class MeasuresPartitionsMigration extends AbstractSchema
{
    private const PGSQL_ONLY = 'PostgreSQL only: the other databases do not list a partition as a table';

    protected string $table = 'measures';

    protected function getStatementsMysql(): array
    {
        throw new LogicException(self::PGSQL_ONLY);
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE measures (id INTEGER, logged DATE) PARTITION BY RANGE (logged)',
            'CREATE TABLE measures_2026_01 PARTITION OF measures '
            . "FOR VALUES FROM ('2026-01-01') TO ('2026-02-01')",
            'CREATE TABLE measures_2026_02 PARTITION OF measures '
            . "FOR VALUES FROM ('2026-02-01') TO ('2026-03-01')",
        ];
    }

    protected function getStatementsSqlite(): array
    {
        throw new LogicException(self::PGSQL_ONLY);
    }
}
