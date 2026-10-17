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
 * The table members, for the model that reads a row (#50).
 */
final class MembersMigration extends AbstractSchema
{
    protected string $table = 'members';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $name, float $score): void
    {
        $this->execute(
            'INSERT INTO members (name, score) VALUES (:name, :score)',
            ['name' => $name, 'score' => $score]
        );
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE members (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL, score DOUBLE)',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE members (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL, score REAL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE members (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50) NOT NULL, score REAL)',
        ];
    }
}
