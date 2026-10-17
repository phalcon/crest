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
 * The table messages, with two keys to users.
 */
final class MessagesMigration extends AbstractSchema
{
    protected string $table = 'messages';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE messages (id INT PRIMARY KEY, recipient_id INT, sender_id INT, '
            . 'FOREIGN KEY (recipient_id) REFERENCES users(id), FOREIGN KEY (sender_id) REFERENCES users(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE messages (id INTEGER PRIMARY KEY, recipient_id INTEGER REFERENCES users(id), '
            . 'sender_id INTEGER REFERENCES users(id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE messages (id INTEGER PRIMARY KEY, recipient_id INTEGER REFERENCES users(id), '
            . 'sender_id INTEGER REFERENCES users(id))',
        ];
    }
}
