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
 * The table payments of the second schema, with a key to the customers of
 * the default schema.
 */
final class BillingPaymentsMigration extends BillingMigration
{
    protected string $table = 'payments';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.payments (id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'customer_id INT NOT NULL, FOREIGN KEY (customer_id) REFERENCES ' . $this->home() . '.customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.payments (id SERIAL PRIMARY KEY, '
            . 'customer_id INTEGER NOT NULL REFERENCES ' . $this->home() . '.customers(id))',
        ];
    }
}
