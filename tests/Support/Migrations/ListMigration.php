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
 * The table list. PHP reserves the class name List.
 */
final class ListMigration extends AbstractSchema
{
    protected string $table = 'list';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE `list` (id INT PRIMARY KEY)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE "list" (id INTEGER PRIMARY KEY)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE list (id INTEGER PRIMARY KEY)'];
    }
}
