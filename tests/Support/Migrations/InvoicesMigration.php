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
 * The table invoices, with a key to customers.
 */
final class InvoicesMigration extends AbstractSchema
{
    protected string $table = 'invoices';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE invoices (id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL, '
            . 'total DECIMAL(10,2), FOREIGN KEY (customer_id) REFERENCES customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE invoices (id SERIAL PRIMARY KEY, '
            . 'customer_id INTEGER NOT NULL REFERENCES customers(id), total DECIMAL(10,2))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'customer_id INTEGER NOT NULL REFERENCES customers(id), total DECIMAL(10,2))',
        ];
    }
}
