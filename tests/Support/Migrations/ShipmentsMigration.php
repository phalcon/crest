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
 * The table shipments, with one key with two columns to lines. SQLite gives
 * one reference for each column of a composite key, so the SQLite table has
 * no key: CompositeKeySqlite gives it (spec 4.3).
 */
final class ShipmentsMigration extends AbstractSchema
{
    protected string $table = 'shipments';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE shipments (id INT PRIMARY KEY, order_id INT, line_no INT, '
            . 'FOREIGN KEY (order_id, line_no) REFERENCES `lines`(order_id, number))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE shipments (id INTEGER PRIMARY KEY, order_id INTEGER, line_no INTEGER, '
            . 'FOREIGN KEY (order_id, line_no) REFERENCES lines(order_id, number))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE shipments (id INTEGER PRIMARY KEY, order_id INTEGER, line_no INTEGER)'];
    }
}
