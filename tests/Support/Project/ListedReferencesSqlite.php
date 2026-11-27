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
use Phalcon\Db\ReferenceInterface;

/**
 * An SQLite adapter that gives the references of each table from a list.
 * SQLite gives one reference for each column of a composite key. MySQL and
 * PostgreSQL give one reference with all its columns, and a test gets that
 * form from this adapter.
 */
final class CompositeKeySqlite extends Sqlite
{
    /**
     * @param array<string, mixed>                    $descriptor
     * @param array<string, list<ReferenceInterface>> $references The table name, and its references
     */
    public function __construct(array $descriptor, private readonly array $references)
    {
        parent::__construct($descriptor);
    }

    /**
     * The references of the list. A table that is not in the list has no
     * references.
     *
     * @return list<ReferenceInterface>
     */
    public function describeReferences(string $table, ?string $schema = null): array
    {
        return $this->references[$table] ?? [];
    }
}
