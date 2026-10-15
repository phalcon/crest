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
use Phalcon\Mvc\Model;

use function preg_match;
use function property_exists;
use function sprintf;

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
     * The column name and its PHP type, in the order of the table. Each
     * column must give a property that the base can declare.
     *
     * @return array<string, string>
     */
    public static function of(AdapterInterface $adapter, string $table): array
    {
        if (false === $adapter->tableExists($table)) {
            throw new Exception(
                sprintf("table '%s' was not found in the database of the 'db' service", $table)
            );
        }

        $properties = [];

        foreach ($adapter->describeColumns($table) as $column) {
            $name = $column->getName();

            if (0 === preg_match(ClassName::PATTERN, $name)) {
                throw new Exception(
                    sprintf("column '%s' of table '%s' is not a usable property name", $name, $table)
                );
            }

            // A typed property with the name of a property of the model is a
            // fatal error, also when the property of the model is protected.
            // property_exists() reads the Phalcon of the project.
            if (true === property_exists(Model::class, $name)) {
                throw new Exception(
                    sprintf(
                        "column '%s' of table '%s' has the name of a property of %s",
                        $name,
                        $table,
                        Model::class
                    )
                );
            }

            $properties[$name] = self::type($column->getType());
        }

        return $properties;
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
