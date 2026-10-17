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
 * The table employees, with a key to itself.
 */
final class EmployeesMigration extends AbstractSchema
{
    protected string $table = 'employees';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE employees (id INT PRIMARY KEY, manager_id INT, '
            . 'FOREIGN KEY (manager_id) REFERENCES employees(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE employees (id INTEGER PRIMARY KEY, manager_id INTEGER REFERENCES employees(id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE employees (id INTEGER PRIMARY KEY, manager_id INTEGER REFERENCES employees(id))',
        ];
    }
}
