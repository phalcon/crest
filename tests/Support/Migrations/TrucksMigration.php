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
 * The table trucks, with a key to drivers, for models.getSet (#70). No other
 * test uses it: ModelGetSetRowsTest loads the generated classes.
 */
final class TrucksMigration extends AbstractSchema
{
    protected string $table = 'trucks';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(int $driverId, string $plate): void
    {
        $this->execute(
            'INSERT INTO trucks (driver_id, plate) VALUES (:driver, :plate)',
            ['driver' => $driverId, 'plate' => $plate]
        );
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE trucks (id INT AUTO_INCREMENT PRIMARY KEY, driver_id INT NOT NULL, plate VARCHAR(20) NULL, '
            . 'FOREIGN KEY (driver_id) REFERENCES drivers(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE trucks (id SERIAL PRIMARY KEY, driver_id INTEGER NOT NULL REFERENCES drivers(id), '
            . 'plate VARCHAR(20) NULL)',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE trucks (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id INTEGER NOT NULL, '
            . 'plate VARCHAR(20) NULL, FOREIGN KEY (driver_id) REFERENCES drivers(id))',
        ];
    }
}
