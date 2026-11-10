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
 * The table users of the second schema. Its columns are not those of the
 * users of the default schema.
 */
final class BillingUsersMigration extends BillingMigration
{
    protected string $table = 'users';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE ' . self::SCHEMA . '.users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(80))'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE ' . self::SCHEMA . '.users (id SERIAL PRIMARY KEY, email VARCHAR(80))'];
    }
}
