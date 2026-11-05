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
 * A table with only a primary key that is not identity.
 */
final class ScaffoldCodesMigration extends AbstractSchema
{
    protected string $table = 'codes';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE codes (code VARCHAR(10) PRIMARY KEY)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE codes (code VARCHAR(10) PRIMARY KEY)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE codes (code VARCHAR(10) PRIMARY KEY)'];
    }
}
