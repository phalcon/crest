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

use LogicException;
use Phalcon\Talon\Database\Schema\AbstractSchema;

/**
 * The table invoices, with two keys to customers that do not name the
 * referenced columns. SQLite only: MySQL rejects such a key, and PostgreSQL
 * uses the primary key (spec 4.3).
 */
final class InvoicesTwoKeysNoColumnsMigration extends AbstractSchema
{
    private const SQLITE_ONLY = 'SQLite only: the key does not name the referenced columns';

    protected string $table = 'invoices';

    protected function getStatementsMysql(): array
    {
        throw new LogicException(self::SQLITE_ONLY);
    }

    protected function getStatementsPgsql(): array
    {
        throw new LogicException(self::SQLITE_ONLY);
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, billing_id INTEGER REFERENCES customers, '
            . 'customer_id INTEGER REFERENCES customers)',
        ];
    }
}
