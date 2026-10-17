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
 * The table payments, with a key to customers.
 */
final class PaymentsMigration extends AbstractSchema
{
    protected string $table = 'payments';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE payments (id INT PRIMARY KEY, customer_id INT, '
            . 'FOREIGN KEY (customer_id) REFERENCES customers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE payments (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE payments (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id))'];
    }
}
