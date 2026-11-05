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
 * A table with a string primary key.
 */
final class ScaffoldCountriesMigration extends AbstractSchema
{
    protected string $table = 'countries';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE countries (code VARCHAR(10) PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE countries (code VARCHAR(10) PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE countries (code VARCHAR(10) PRIMARY KEY, name VARCHAR(50) NOT NULL)'];
    }
}
