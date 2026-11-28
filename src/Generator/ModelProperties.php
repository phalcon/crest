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

namespace Crest\Generator;

use Crest\Console\Exceptions\Exception;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Column;

use function implode;
use function sprintf;
use function str_contains;

/**
 * The properties of a model that make:model writes: the columns of its table,
 * with a PHP type for each one.
 *
 * Phalcon gives the type of a column as a Column::TYPE_* constant. type() maps
 * the constants for numbers and booleans, and gives `string` for each other
 * type. So a type that a later version of Phalcon adds does not stop the
 * command. The constants of the map exist in Phalcon 4 and later.
 */
final class ModelProperties
{
    /**
     * The column name and its PHP type, in the order of the table. The
     * property names come from ModelNames, which checks them (#53). A null
     * schema is the default schema of the connection (#69).
     *
     * A view is a table here (#88). On SQLite, tableExists() does not find a
     * view (cphalcon #17768), so crest also asks viewExists().
     *
     * @return array<string, string>
     */
    public static function of(AdapterInterface $adapter, string $table, ?string $schema = null): array
    {
        if (false === $adapter->tableExists($table, $schema) && false === $adapter->viewExists($table, $schema)) {
            throw new Exception(
                null === $schema
                    ? sprintf("table '%s' was not found in the database of the 'db' service", $table)
                    : sprintf("table '%s' was not found in the schema '%s' of the 'db' service", $table, $schema)
            );
        }

        $properties = [];

        foreach ($adapter->describeColumns($table, $schema) as $column) {
            $properties[$column->getName()] = self::type($column->getType());
        }

        return $properties;
    }

    /**
     * of() for a name of ModelNames (#86): <schema>.<table>, or a table of
     * the default schema. With a list of schemas, a name with no schema that
     * the default schema does not have gets a hint: the names of the other
     * schemas of the list that have the table.
     *
     * @return array<string, string>
     */
    public static function ofName(AdapterInterface $adapter, ModelNames $names, string $name): array
    {
        $table  = $names->tableOf($name);
        $schema = $names->schemaOf($name);

        try {
            return self::of($adapter, $table, $schema);
        } catch (Exception $exception) {
            if (false === $names->isList() || true === str_contains($name, '.')) {
                throw $exception;
            }

            $found = [];

            foreach ($names->schemas() as $other) {
                if (
                    $other !== $schema
                    && (true === $adapter->tableExists($table, $other) || true === $adapter->viewExists($table, $other))
                ) {
                    $found[] = $names->name($other, $table);
                }
            }

            if ([] === $found) {
                throw $exception;
            }

            throw new Exception(
                $exception->getMessage() . '; did you mean ' . implode(', ', $found) . '?',
                previous: $exception
            );
        }
    }

    /**
     * The PHP type for a column type. DECIMAL gives `string`, so the value
     * keeps its precision. The parameter is int|string because
     * Column::getType() of Phalcon v6 declares `int | string`.
     */
    public static function type(int | string $type): string
    {
        return match ($type) {
            Column::TYPE_BIGINTEGER,
            Column::TYPE_INTEGER,
            Column::TYPE_MEDIUMINTEGER,
            Column::TYPE_SMALLINTEGER,
            Column::TYPE_TINYINTEGER => 'int',
            Column::TYPE_DOUBLE,
            Column::TYPE_FLOAT   => 'float',
            Column::TYPE_BOOLEAN => 'bool',
            default              => 'string',
        };
    }
}
