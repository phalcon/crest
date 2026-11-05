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
 * A table with a binary column, which has no field in the form.
 */
final class ScaffoldAvatarsMigration extends AbstractSchema
{
    protected string $table = 'avatars';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE avatars (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(50), picture BLOB)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE avatars (id SERIAL PRIMARY KEY, title VARCHAR(50), picture BYTEA)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE avatars (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(50), picture BLOB)'];
    }
}
