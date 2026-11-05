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
 * A table whose primary key has the name of a PHQL word.
 */
final class ScaffoldSlotsMigration extends AbstractSchema
{
    protected string $table = 'slots';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE slots (`order` INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE slots ("order" SERIAL PRIMARY KEY, name VARCHAR(50))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE slots ("order" INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50))'];
    }
}
