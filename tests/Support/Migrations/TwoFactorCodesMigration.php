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
 * The table 2fa_codes, with a key to users. Its name gives no class name.
 */
final class TwoFactorCodesMigration extends AbstractSchema
{
    protected string $table = '2fa_codes';

    protected function getStatementsMysql(): array
    {
        return [
            'CREATE TABLE `2fa_codes` (id INT PRIMARY KEY, user_id INT, '
            . 'FOREIGN KEY (user_id) REFERENCES users(id))',
        ];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE TABLE "2fa_codes" (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id))'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE TABLE "2fa_codes" (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id))'];
    }
}
