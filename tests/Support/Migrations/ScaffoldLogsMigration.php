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
 * A table with no primary key.
 */
final class ScaffoldLogsMigration extends AbstractSchema
{
    protected string $table = 'logs';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE logs (message VARCHAR(50))'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE logs (message VARCHAR(50))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE logs (message VARCHAR(50))'];
    }
}
