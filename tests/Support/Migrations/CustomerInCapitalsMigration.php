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
 * The table CUSTOMER, with a key to invoices. Its name is in capitals.
 */
final class CustomerInCapitalsMigration extends AbstractSchema
{
    protected string $table = 'CUSTOMER';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE `CUSTOMER` (id INT PRIMARY KEY, invoice_id INT, '
            . 'FOREIGN KEY (invoice_id) REFERENCES invoices(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE "CUSTOMER" (id INTEGER PRIMARY KEY, invoice_id INTEGER REFERENCES invoices(id))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE CUSTOMER (id INTEGER PRIMARY KEY, invoice_id INTEGER REFERENCES invoices(id))'];
    }
}
