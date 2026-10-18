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
 * The table people, for the names that read rows (#53). No other test uses
 * it: the test loads the generated classes.
 */
final class PeopleMigration extends AbstractSchema
{
    protected string $table = 'people';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $firstName): void
    {
        $this->execute('INSERT INTO people (first_name) VALUES (:name)', ['name' => $firstName]);
    }

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE people (id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE people (id SERIAL PRIMARY KEY, first_name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE people (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name VARCHAR(50) NOT NULL)'];
    }
}
