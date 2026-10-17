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
 * A second migration of the table users: it adds the column email to the
 * table of UsersMigration.
 */
final class UsersWithEmailMigration extends AbstractSchema
{
    protected string $table = 'users';

    protected function getStatementsMysql(): array
    {
        return ['ALTER TABLE users ADD COLUMN email VARCHAR(100)'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['ALTER TABLE users ADD COLUMN email VARCHAR(100)'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['ALTER TABLE users ADD COLUMN email VARCHAR(100)'];
    }
}
