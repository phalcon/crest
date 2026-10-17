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
 * The table books, with a key to authors, for the relations that read rows
 * (#51).
 */
final class BooksMigration extends AbstractSchema
{
    protected string $table = 'books';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $title, int $authorId): void
    {
        $this->execute(
            'INSERT INTO books (title, author_id) VALUES (:title, :author)',
            ['title' => $title, 'author' => $authorId]
        );
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE books (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(50) NOT NULL, '
            . 'author_id INT NOT NULL, FOREIGN KEY (author_id) REFERENCES authors(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE books (id SERIAL PRIMARY KEY, title VARCHAR(50) NOT NULL, '
            . 'author_id INTEGER NOT NULL REFERENCES authors(id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE books (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(50) NOT NULL, '
            . 'author_id INTEGER NOT NULL REFERENCES authors(id))',
        ];
    }
}
