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
 * The table vendors of the default schema. A test of the second schema keeps
 * it empty: a model that reads it in place of the vendors of the second
 * schema finds no row (#69).
 */
final class VendorsMigration extends AbstractSchema
{
    protected string $table = 'vendors';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE vendors (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE vendors (id SERIAL PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE vendors (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50) NOT NULL)'];
    }
}
