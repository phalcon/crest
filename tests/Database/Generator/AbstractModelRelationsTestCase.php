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

namespace Crest\Tests\Database\Generator;

use Crest\Generator\ModelRelations;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Phalcon\Db\Adapter\AdapterInterface;

/**
 * The base of the tests of the relations of make:model (#51, spec 3.1 to
 * 3.4). A test gives ModelRelations::of() the columns that its checks need.
 * The table of a migration can have more columns: the relations read only the
 * keys of the database.
 *
 * @phpstan-import-type Relation from ModelRelations
 */
abstract class AbstractModelRelationsTestCase extends AbstractDatabaseTestCase
{
    /**
     * The base that the errors about the attributes name.
     */
    protected const BASE_FILE = '/app/src/Models/Base/InvoicesBase.php';

    protected const INVOICE_KEY = 'belongsTo invoices(customer_id) -> customers(id)';

    private const NAMESPACE = 'App\\Models';

    /**
     * @param list<string> $fields
     * @param list<string> $referenced
     *
     * @return Relation
     */
    protected function relation(
        string $kind,
        array $fields,
        string $class,
        array $referenced,
        string $alias,
        string $key
    ): array {
        return [
            'kind'       => $kind,
            'fields'     => $fields,
            'class'      => $class,
            'referenced' => $referenced,
            'alias'      => $alias,
            'key'        => $key,
        ];
    }

    /**
     * The relations of the table in the namespace App\Models, with the
     * attributes of $stored in the base self::BASE_FILE.
     *
     * @param list<string>                                   $columns
     * @param array<string, array{alias: string, line: int}> $stored
     */
    protected function relationsOf(
        AdapterInterface $adapter,
        string $table,
        array $columns,
        array $stored = []
    ): ModelRelations {
        return ModelRelations::of($adapter, $table, self::NAMESPACE, $columns, $stored, self::BASE_FILE);
    }
}
