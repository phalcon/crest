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
 * The table drivers, for models.getSet (#70). Only ModelGetSetRowsTest loads
 * the generated classes; the other tests only read the text of the base.
 */
final class DriversMigration extends AbstractSchema
{
    protected string $table = 'drivers';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $firstName, int $age): void
    {
        $this->execute(
            'INSERT INTO drivers (first_name, age) VALUES (:name, :age)',
            ['name' => $firstName, 'age' => $age]
        );
    }

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE drivers (id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(50) NULL, age INT NULL)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE drivers (id SERIAL PRIMARY KEY, first_name VARCHAR(50) NULL, age INTEGER NULL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE drivers (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name VARCHAR(50) NULL, '
            . 'age INTEGER NULL)',
        ];
    }
}
