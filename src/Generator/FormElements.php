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

use Phalcon\Db\Column;
use Phalcon\Db\ColumnInterface;

use function array_unique;
use function in_array;
use function sort;
use function sprintf;
use function str_replace;
use function strtolower;
use function ucfirst;
use function var_export;

use const SORT_STRING;

/**
 * The code of a form for the columns of a table (make:scaffold, #56). It reads
 * no file and no database, so a unit test can check each row of the map.
 *
 * Each filter gives the PHP type of the property, because Form.php of v6 has
 * strict_types=1 and writes the filtered value into the property. A string
 * column has the filter `trim`: the `string` filter changes the characters of
 * the value, and the views escape it on output.
 */
final class FormElements
{
    /**
     * The other arguments of a number element that can have a fraction.
     */
    private const ANY_STEP = ", ['step' => 'any']";

    /**
     * The column types that have no field in a form.
     */
    private const BINARY = [
        Column::TYPE_BINARY,
        Column::TYPE_BLOB,
        Column::TYPE_BYTEA,
        Column::TYPE_LONGBLOB,
        Column::TYPE_MEDIUMBLOB,
        Column::TYPE_TINYBLOB,
        Column::TYPE_VARBINARY,
    ];

    /**
     * The other arguments of a boolean element. A Select and not a Check: a
     * Check that is not checked sends no value. useEmpty lets the search form
     * have no value for the column.
     */
    private const YES_NO = ", [0 => 'No', 1 => 'Yes'], ['useEmpty' => true]";

    /**
     * @param string       $code      The lines of initialize(), each with its new line
     * @param int          $editCount The elements of the edit form: no primary key
     * @param string       $imports   One use line for each element class, sorted
     * @param list<string> $skipped   The binary columns, in the order of the table
     */
    private function __construct(
        public readonly string $code,
        public readonly int $editCount,
        public readonly string $imports,
        public readonly array $skipped,
    ) {
    }

    /**
     * @param array<ColumnInterface> $columns In the order of the table
     * @param array<string, string>  $names   The column and its property
     */
    public static function of(array $columns, array $names): self
    {
        $code      = '';
        $editCount = 0;
        $imports   = [];
        $skipped   = [];

        foreach ($columns as $column) {
            $name = $column->getName();
            $type = $column->getType();

            if (true === $column->isAutoIncrement()) {
                continue;
            }

            if (true === in_array($type, self::BINARY, true)) {
                $skipped[] = $name;

                continue;
            }

            [$element, $arguments, $filter] = self::element($name, $type);

            $imports[] = $element;

            $line = sprintf(
                '$this->add((new %s(%s%s))->setLabel(%s)->setFilters([%s]));',
                $element,
                var_export($names[$name], true),
                $arguments,
                var_export(ucfirst(str_replace('_', ' ', $name)), true),
                var_export($filter, true)
            );

            if (true === $column->isPrimary()) {
                // Only on the new form: the edit form has no primary key.
                $code .= "        if (null === \$entity) {\n            " . $line . "\n        }\n";

                continue;
            }

            $code .= '        ' . $line . "\n";
            $editCount++;
        }

        return new self($code, $editCount, self::imports($imports), $skipped);
    }

    /**
     * The class of the element, its other arguments, and its filter. The name
     * of the column comes first, then its type (spec 3.6).
     *
     * @param int|string $type int|string because Column::getType() of Phalcon
     *                         v6 declares `int | string`.
     *
     * @return array{string, string, string}
     */
    private static function element(string $name, int | string $type): array
    {
        if ('email' === strtolower($name)) {
            return ['Email', '', 'email'];
        }

        return match ($type) {
            Column::TYPE_BIGINTEGER,
            Column::TYPE_INTEGER,
            Column::TYPE_MEDIUMINTEGER,
            Column::TYPE_SMALLINTEGER,
            Column::TYPE_TINYINTEGER => ['Numeric', '', 'int'],
            Column::TYPE_DOUBLE,
            Column::TYPE_FLOAT   => ['Numeric', self::ANY_STEP, 'float'],
            // The property is a string (ModelProperties).
            Column::TYPE_DECIMAL => ['Numeric', self::ANY_STEP, 'trim'],
            Column::TYPE_BOOLEAN => ['Select', self::YES_NO, 'bool'],
            Column::TYPE_DATE    => ['Date', '', 'trim'],
            Column::TYPE_JSON,
            Column::TYPE_JSONB,
            Column::TYPE_LONGTEXT,
            Column::TYPE_MEDIUMTEXT,
            Column::TYPE_TEXT,
            Column::TYPE_TINYTEXT => ['TextArea', '', 'trim'],
            default               => ['Text', '', 'trim'],
        };
    }

    /**
     * @param list<string> $elements
     */
    private static function imports(array $elements): string
    {
        $elements = array_unique($elements);

        sort($elements, SORT_STRING);

        $imports = '';

        foreach ($elements as $element) {
            $imports .= 'use Phalcon\\Forms\\Element\\' . $element . ";\n";
        }

        return $imports;
    }
}
