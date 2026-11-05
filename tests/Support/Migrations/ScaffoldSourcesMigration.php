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
 * A table with a column that gives the name of a method of Phalcon\Mvc\Model.
 */
final class ScaffoldSourcesMigration extends AbstractSchema
{
    protected string $table = 'sources';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE sources (id INT AUTO_INCREMENT PRIMARY KEY, source VARCHAR(50))'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE sources (id SERIAL PRIMARY KEY, source VARCHAR(50))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE sources (id INTEGER PRIMARY KEY AUTOINCREMENT, source VARCHAR(50))'];
    }
}
