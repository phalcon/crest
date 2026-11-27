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
 * Before cphalcon 5.24.0 and phalcon/phalcon 6.0.0, a key with no referenced
 * columns (`REFERENCES customers`) gave null for the column. A test gets that
 * form from this adapter on each version.
 */
final class ListedReferencesSqlite extends Sqlite
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
