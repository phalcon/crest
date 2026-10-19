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
 * The table totals. A column name is a number, so PHP makes it an integer
 * key, and it is not a usable property name.
 */
final class TotalsMigration extends AbstractSchema
{
    protected string $table = 'totals';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE totals (id INT PRIMARY KEY, `2024` INT)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE totals (id INTEGER PRIMARY KEY, "2024" INTEGER)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE totals (id INTEGER PRIMARY KEY, "2024" INTEGER)'];
    }
}
