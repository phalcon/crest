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
 * The table invoices, with one key column to customers. The test gives the
 * name of the key column.
 */
final class InvoicesKeyColumnMigration extends AbstractSchema
{
    protected string $table = 'invoices';

    private string $column = 'customer_id';

    /**
     * The name of the key column. The constructor of a migration is final, so
     * the test gives the name here.
     */
    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE invoices (id INT PRIMARY KEY, `' . $this->column . '` INT, '
            . 'FOREIGN KEY (`' . $this->column . '`) REFERENCES customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, "' . $this->column . '" INTEGER '
            . 'REFERENCES customers(id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, "' . $this->column . '" INTEGER '
            . 'REFERENCES customers(id))',
        ];
    }
}
