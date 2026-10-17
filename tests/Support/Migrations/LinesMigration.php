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

use Phalcon\Talon\Database\Schema\AbstractSchema;

/**
 * The table lines. Its primary key has two columns, and shipments has a key
 * to them. The SQLite table has no primary key, as the composite key test
 * with CompositeKeySqlite had.
 */
final class LinesMigration extends AbstractSchema
{
    protected string $table = 'lines';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE `lines` (order_id INT, number INT, PRIMARY KEY (order_id, number))'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE lines (order_id INTEGER, number INTEGER, PRIMARY KEY (order_id, number))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE lines (order_id INTEGER, number INTEGER)'];
    }
}
