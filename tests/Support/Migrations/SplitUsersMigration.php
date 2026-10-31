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
 * The table us_ers. It gives the class UsErs, and PHP sees UsErs and Users
 * as one class (#71). On each driver, us_ers and users are two tables.
 */
final class SplitUsersMigration extends AbstractSchema
{
    protected string $table = 'us_ers';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE us_ers (id INT PRIMARY KEY)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE us_ers (id INTEGER PRIMARY KEY)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE us_ers (id INTEGER PRIMARY KEY)'];
    }
}
