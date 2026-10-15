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

namespace Crest\Tests\Unit\Generator;

use Crest\Generator\ModelProperties;
use Phalcon\Db\Column;
use PHPUnit\Framework\TestCase;

/**
 * The type map of make:model. SQLite cannot give TYPE_DOUBLE, TYPE_BOOLEAN or
 * the small integer types through describeColumns(), so this test holds those
 * rows of the map.
 */
final class ModelPropertiesTest extends TestCase
{
    /**
     * The Phalcon column type, and the PHP type that it gives.
     *
     * @return iterable<string, array{int|string, string}>
     */
    public static function types(): iterable
    {
        yield 'integer' => [Column::TYPE_INTEGER, 'int'];
        yield 'big integer' => [Column::TYPE_BIGINTEGER, 'int'];
        yield 'small integer' => [Column::TYPE_SMALLINTEGER, 'int'];
        yield 'medium integer' => [Column::TYPE_MEDIUMINTEGER, 'int'];
        yield 'tiny integer' => [Column::TYPE_TINYINTEGER, 'int'];
        yield 'float' => [Column::TYPE_FLOAT, 'float'];
        yield 'double' => [Column::TYPE_DOUBLE, 'float'];
        yield 'boolean' => [Column::TYPE_BOOLEAN, 'bool'];
        yield 'varchar' => [Column::TYPE_VARCHAR, 'string'];
        yield 'decimal keeps its precision' => [Column::TYPE_DECIMAL, 'string'];
        yield 'json' => [Column::TYPE_JSON, 'string'];
        yield 'a type given as a string' => ['custom', 'string'];
    }

    /**
     * @dataProvider types
     */
    public function testTypeMapsAColumnTypeToAPhpType(int | string $type, string $expected): void
    {
        $this->assertSame($expected, ModelProperties::type($type));
    }
}
