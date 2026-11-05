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
 * A table with a primary key of two columns.
 */
final class ScaffoldPairsMigration extends AbstractSchema
{
    protected string $table = 'pairs';

    protected function getStatementsMysql(): array
    {
        return ['CREATE TABLE pairs (left_id INT NOT NULL, right_id INT NOT NULL, PRIMARY KEY (left_id, right_id))'];
    }

    protected function getStatementsPgsql(): array
    {
        return [
            'CREATE TABLE pairs (left_id INTEGER NOT NULL, right_id INTEGER NOT NULL, '
            . 'PRIMARY KEY (left_id, right_id))',
        ];
    }

    protected function getStatementsSqlite(): array
    {
        return [
            'CREATE TABLE pairs (left_id INTEGER NOT NULL, right_id INTEGER NOT NULL, '
            . 'PRIMARY KEY (left_id, right_id))',
        ];
    }
}
