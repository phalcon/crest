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
 * The table vendors of the second schema, for the relations that read rows
 * (#69).
 */
final class BillingVendorsMigration extends BillingMigration
{
    protected string $table = 'vendors';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $name): void
    {
        $this->execute('INSERT INTO ' . self::SCHEMA . '.vendors (name) VALUES (:name)', ['name' => $name]);
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.vendors (id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'name VARCHAR(50) NOT NULL)',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE ' . self::SCHEMA . '.vendors (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }
}
