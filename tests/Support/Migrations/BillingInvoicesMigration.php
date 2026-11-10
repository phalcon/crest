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

/**
 * The table invoices of the second schema, with a key to the customers of
 * the same schema.
 */
final class BillingInvoicesMigration extends BillingMigration
{
    protected string $table = 'invoices';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.invoices (id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'customer_id INT NOT NULL, total DECIMAL(10,2), '
            . 'FOREIGN KEY (customer_id) REFERENCES ' . self::SCHEMA . '.customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.invoices (id SERIAL PRIMARY KEY, '
            . 'customer_id INTEGER NOT NULL REFERENCES ' . self::SCHEMA . '.customers(id), total DECIMAL(10,2))',
        ];
    }
}
