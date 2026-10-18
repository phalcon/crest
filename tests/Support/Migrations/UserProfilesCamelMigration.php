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
 * The table UserProfiles. It gives the class of the table user_profiles.
 * PostgreSQL keeps the case of a name only in quotes.
 */
final class UserProfilesCamelMigration extends AbstractSchema
{
    protected string $table = 'UserProfiles';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE `UserProfiles` (id INT AUTO_INCREMENT PRIMARY KEY)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE "UserProfiles" (id SERIAL PRIMARY KEY)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE "UserProfiles" (id INTEGER PRIMARY KEY AUTOINCREMENT)'];
    }
}
