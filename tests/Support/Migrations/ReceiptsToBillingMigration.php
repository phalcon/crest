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
 * The table receipts of the default schema, with a key to the customers of
 * the second schema.
 */
final class ReceiptsToBillingMigration extends BillingMigration
{
    protected string $table = 'receipts';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE receipts (id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL, '
            . 'FOREIGN KEY (customer_id) REFERENCES ' . self::SCHEMA . '.customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE receipts (id SERIAL PRIMARY KEY, '
            . 'customer_id INTEGER NOT NULL REFERENCES ' . self::SCHEMA . '.customers(id))',
        ];
    }
}
