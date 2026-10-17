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
 * The table notes, with a key to users. The key column has a quote in its
 * name.
 */
final class NotesWithQuoteMigration extends AbstractSchema
{
    protected string $table = 'notes';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE notes (id INT PRIMARY KEY, `it\'s_id` INT, FOREIGN KEY (`it\'s_id`) REFERENCES users(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE notes (id INTEGER PRIMARY KEY, "it\'s_id" INTEGER REFERENCES users(id))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE notes (id INTEGER PRIMARY KEY, "it\'s_id" INTEGER REFERENCES users(id))'];
    }
}
