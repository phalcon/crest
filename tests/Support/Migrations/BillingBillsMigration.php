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
 * The table bills of the second schema, with a key to the vendors of the
 * same schema, for the relations that read rows (#69).
 */
final class BillingBillsMigration extends BillingMigration
{
    protected string $table = 'bills';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $title, int $vendorId): void
    {
        $this->execute(
            'INSERT INTO ' . self::SCHEMA . '.bills (title, vendor_id) VALUES (:title, :vendor)',
            ['title' => $title, 'vendor' => $vendorId]
        );
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.bills (id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'title VARCHAR(50) NOT NULL, vendor_id INT NOT NULL, '
            . 'FOREIGN KEY (vendor_id) REFERENCES ' . self::SCHEMA . '.vendors(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE ' . self::SCHEMA . '.bills (id SERIAL PRIMARY KEY, title VARCHAR(50) NOT NULL, '
            . 'vendor_id INTEGER NOT NULL REFERENCES ' . self::SCHEMA . '.vendors(id))',
        ];
    }
}
