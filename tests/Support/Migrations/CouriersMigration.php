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
 * The table couriers, for models.getSet with camelCase (#70). No other test
 * uses it: ModelGetSetRowsTest loads the generated classes.
 */
final class CouriersMigration extends AbstractSchema
{
    protected string $table = 'couriers';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $firstName, int $age): void
    {
        $this->execute(
            'INSERT INTO couriers (first_name, age) VALUES (:name, :age)',
            ['name' => $firstName, 'age' => $age]
        );
    }

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE couriers (id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(50) NULL, age INT NULL)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE couriers (id SERIAL PRIMARY KEY, first_name VARCHAR(50) NULL, age INTEGER NULL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE couriers (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name VARCHAR(50) NULL, '
            . 'age INTEGER NULL)',
        ];
    }
}
