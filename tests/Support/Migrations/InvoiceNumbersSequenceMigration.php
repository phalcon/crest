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
 * The sequence invoice_numbers. MariaDB only: MySQL and SQLite have no
 * sequences, and PostgreSQL does not list a sequence as a table (#101).
 */
final class InvoiceNumbersSequenceMigration extends AbstractSchema
{
    private const MARIADB_ONLY = 'MariaDB only: MySQL and SQLite have no sequences';

    protected string $table = 'invoice_numbers';

    protected function getStatementsMysql(): array
    {
        return ['CREATE SEQUENCE invoice_numbers'];
    }

    protected function getStatementsPgsql(): array
    {
        throw new LogicException(self::MARIADB_ONLY);
    }

    protected function getStatementsSqlite(): array
    {
        throw new LogicException(self::MARIADB_ONLY);
    }
}
