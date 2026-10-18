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

namespace Crest\Tests\Support\Project;

use Phalcon\Db\Adapter\Pdo\Sqlite;
use RuntimeException;

/**
 * An SQLite adapter that stops when crest reads the keys. A test with it
 * shows that crest checks the table and its columns before it reads the
 * keys of the database.
 */
final class NoKeyReadSqlite extends Sqlite
{
    /**
     * @return list<\Phalcon\Db\ReferenceInterface>
     */
    public function describeReferences(string $table, ?string $schema = null): array
    {
        throw new RuntimeException('crest read the keys');
    }
}
