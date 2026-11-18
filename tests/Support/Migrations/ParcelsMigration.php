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
 * The table parcels, for models.getSet with --property and with names for
 * which camelize() of Phalcon and studly() of crest can differ: a first
 * underscore and a digit after an underscore (#70). No other test uses it:
 * ModelGetSetRowsTest loads the generated classes.
 */
final class ParcelsMigration extends AbstractSchema
{
    protected string $table = 'parcels';

    /**
     * Adds one row. The database gives the id.
     */
    public function insert(string $firstName, string $note, string $line): void
    {
        $this->execute(
            'INSERT INTO parcels (first_name, _note, line_1) VALUES (:name, :note, :line)',
            ['name' => $firstName, 'note' => $note, 'line' => $line]
        );
    }

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE parcels (id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(50) NULL, '
            . '_note VARCHAR(50) NULL, line_1 VARCHAR(50) NULL)',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE parcels (id SERIAL PRIMARY KEY, first_name VARCHAR(50) NULL, _note VARCHAR(50) NULL, '
            . 'line_1 VARCHAR(50) NULL)',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE parcels (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name VARCHAR(50) NULL, '
            . '_note VARCHAR(50) NULL, line_1 VARCHAR(50) NULL)',
        ];
    }
}
