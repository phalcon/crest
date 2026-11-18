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
 * The table vans, for Model::assign() with no setters on a protected property
 * (#70). No other test uses it: ModelGetSetRowsTest loads the generated
 * classes.
 */
final class VansMigration extends AbstractSchema
{
    protected string $table = 'vans';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(int $age): void
    {
        $this->execute('INSERT INTO vans (age) VALUES (:age)', ['age' => $age]);
    }

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE vans (id INT AUTO_INCREMENT PRIMARY KEY, age INT NULL)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE vans (id SERIAL PRIMARY KEY, age INTEGER NULL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE vans (id INTEGER PRIMARY KEY AUTOINCREMENT, age INTEGER NULL)'];
    }
}
