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
 * A table with two words in its name and in the name of a column.
 */
final class ScaffoldProfilesMigration extends AbstractSchema
{
    protected string $table = 'user_profiles';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE user_profiles (id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(50) NOT NULL, age INT)',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE user_profiles (id SERIAL PRIMARY KEY, first_name VARCHAR(50) NOT NULL, age INTEGER)'];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE user_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'first_name VARCHAR(50) NOT NULL, age INTEGER)',
        ];
    }
}
