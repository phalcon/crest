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
 * The table kinds, with the common column types of each dialect, for the type
 * map of make:model.
 */
final class KindsMigration extends AbstractSchema
{
    protected string $table = 'kinds';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE kinds (id INT AUTO_INCREMENT PRIMARY KEY, tiny_number TINYINT, '
            . 'small_number SMALLINT, medium_number MEDIUMINT, big_number BIGINT, amount FLOAT, '
            . 'measure DOUBLE, flag BOOLEAN, price DECIMAL(10,2), label VARCHAR(50), body TEXT, born DATE, '
            . 'created DATETIME, changed TIMESTAMP NULL, data JSON)',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE kinds (id SERIAL PRIMARY KEY, small_number SMALLINT, big_number BIGINT, '
            . 'amount REAL, measure DOUBLE PRECISION, flag BOOLEAN, price NUMERIC(10,2), label VARCHAR(50), '
            . 'body TEXT, born DATE, created TIMESTAMP, data JSONB, code UUID)',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE kinds (id INTEGER PRIMARY KEY AUTOINCREMENT, small_number SMALLINT, '
            . 'big_number BIGINT, amount FLOAT, ratio REAL, measure DOUBLE, flag BOOLEAN, price DECIMAL(10,2), '
            . 'label VARCHAR(50), body TEXT, born DATE, created DATETIME)',
        ];
    }
}
