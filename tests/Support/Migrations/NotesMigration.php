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
 * The table notes, with a key to list.
 */
final class NotesMigration extends AbstractSchema
{
    protected string $table = 'notes';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE notes (id INT PRIMARY KEY, list_id INT, FOREIGN KEY (list_id) REFERENCES `list`(id))'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE notes (id INTEGER PRIMARY KEY, list_id INTEGER REFERENCES "list"(id))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE notes (id INTEGER PRIMARY KEY, list_id INTEGER REFERENCES list(id))'];
    }
}
