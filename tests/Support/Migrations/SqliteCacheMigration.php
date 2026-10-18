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
 * The table sqlite_cache. On MySQL and PostgreSQL, it is a usual name.
 * SQLite rejects it, so only the server suites use this table.
 */
final class SqliteCacheMigration extends AbstractSchema
{
    protected string $table = 'sqlite_cache';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE sqlite_cache (id INT AUTO_INCREMENT PRIMARY KEY)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE sqlite_cache (id SERIAL PRIMARY KEY)'];
    }

    protected function getStatementsSqlite(): array
    {
        return [];
    }
}
