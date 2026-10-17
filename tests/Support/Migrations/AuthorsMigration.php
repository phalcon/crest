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
 * The table authors, for the relations that read rows (#51).
 */
final class AuthorsMigration extends AbstractSchema
{
    protected string $table = 'authors';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $name): void
    {
        $this->execute('INSERT INTO authors (name) VALUES (:name)', ['name' => $name]);
    }

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE authors (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE authors (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE authors (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50) NOT NULL)'];
    }
}
