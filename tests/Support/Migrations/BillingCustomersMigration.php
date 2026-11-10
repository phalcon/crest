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
 * The table customers of the second schema.
 */
final class BillingCustomersMigration extends BillingMigration
{
    protected string $table = 'customers';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.customers (id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'name VARCHAR(50) NOT NULL)',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE ' . self::SCHEMA . '.customers (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }
}
