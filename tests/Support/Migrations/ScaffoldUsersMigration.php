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
 * The table users for make:scaffold, with one column for each kind of field.
 */
final class ScaffoldUsersMigration extends AbstractSchema
{
    protected string $table = 'users';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL, age INT, '
            . 'active BOOLEAN, email VARCHAR(80), price DECIMAL(10,2), score DOUBLE, born DATE, bio TEXT)',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE users (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL, age INTEGER, '
            . 'active BOOLEAN, email VARCHAR(80), price DECIMAL(10,2), score REAL, born DATE, bio TEXT)',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50) NOT NULL, age INTEGER, '
            . 'active BOOLEAN, email VARCHAR(80), price DECIMAL(10,2), score REAL, born DATE, bio TEXT)',
        ];
    }
}
