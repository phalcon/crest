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
 * The table boxes. The column container has the name of a property of
 * Phalcon\Mvc\Model.
 */
final class BoxesMigration extends AbstractSchema
{
    protected string $table = 'boxes';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE boxes (id INT PRIMARY KEY, container TEXT)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE boxes (id INTEGER PRIMARY KEY, container TEXT)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE boxes (id INTEGER PRIMARY KEY, container TEXT)'];
    }
}
