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
 * The table pets, with a key to people, for the names that read rows (#53).
 * No other test uses it: the test loads the generated classes.
 */
final class PetsMigration extends AbstractSchema
{
    protected string $table = 'pets';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $name, int $personId): void
    {
        $this->execute(
            'INSERT INTO pets (pet_name, person_id) VALUES (:name, :person)',
            ['name' => $name, 'person' => $personId]
        );
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE pets (id INT AUTO_INCREMENT PRIMARY KEY, pet_name VARCHAR(50) NOT NULL, '
            . 'person_id INT NOT NULL, FOREIGN KEY (person_id) REFERENCES people(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE pets (id SERIAL PRIMARY KEY, pet_name VARCHAR(50) NOT NULL, '
            . 'person_id INTEGER NOT NULL REFERENCES people(id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE pets (id INTEGER PRIMARY KEY AUTOINCREMENT, pet_name VARCHAR(50) NOT NULL, '
            . 'person_id INTEGER NOT NULL REFERENCES people(id))',
        ];
    }
}
