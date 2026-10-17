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
 * The table invoices, with a key to customers and a text column with the
 * name of the alias Customer. The test can give the name in another case.
 */
final class InvoicesWithCustomerColumnMigration extends AbstractSchema
{
    protected string $table = 'invoices';

    private string $column = 'customer';

    /**
     * The name of the text column, for example CUSTOMER.
     */
    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE invoices (id INT AUTO_INCREMENT PRIMARY KEY, `' . $this->column . '` TEXT, '
            . 'customer_id INT, FOREIGN KEY (customer_id) REFERENCES customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE invoices (id SERIAL PRIMARY KEY, "' . $this->column . '" TEXT, '
            . 'customer_id INTEGER REFERENCES customers(id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY AUTOINCREMENT, "' . $this->column . '" TEXT, '
            . 'customer_id INTEGER REFERENCES customers(id))',
        ];
    }
}
