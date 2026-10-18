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
 * The table user-profiles. Its name gives no class.
 */
final class UserProfilesDashMigration extends AbstractSchema
{
    protected string $table = 'user-profiles';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE `user-profiles` (id INT PRIMARY KEY)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE "user-profiles" (id INTEGER PRIMARY KEY)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE "user-profiles" (id INTEGER PRIMARY KEY)'];
    }
}
