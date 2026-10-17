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
 * The table odd. A column name has a space, so it is not a usable property
 * name.
 */
final class OddMigration extends AbstractSchema
{
    protected string $table = 'odd';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE odd (id INT PRIMARY KEY, `first name` TEXT)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE odd (id INTEGER PRIMARY KEY, "first name" TEXT)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE odd (id INTEGER PRIMARY KEY, "first name" TEXT)'];
    }
}
