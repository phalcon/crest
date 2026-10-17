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
 * The table user_profiles. Its name has an underscore.
 */
final class UserProfilesMigration extends AbstractSchema
{
    protected string $table = 'user_profiles';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE user_profiles (id INT AUTO_INCREMENT PRIMARY KEY)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE user_profiles (id SERIAL PRIMARY KEY)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE user_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT)'];
    }
}
