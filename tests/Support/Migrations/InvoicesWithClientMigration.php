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
 * The table invoices, with the keys client_id and customer_id to customers.
 */
final class InvoicesWithClientMigration extends AbstractSchema
{
    protected string $table = 'invoices';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE invoices (id INT PRIMARY KEY, client_id INT, customer_id INT, '
            . 'FOREIGN KEY (client_id) REFERENCES customers(id), '
            . 'FOREIGN KEY (customer_id) REFERENCES customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, client_id INTEGER REFERENCES customers(id), '
            . 'customer_id INTEGER REFERENCES customers(id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, client_id INTEGER REFERENCES customers(id), '
            . 'customer_id INTEGER REFERENCES customers(id))',
        ];
    }
}
