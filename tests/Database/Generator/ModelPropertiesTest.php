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

use Crest\Generator\ModelProperties;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\KindsMigration;
use Phalcon\Talon\Database\Dialect;

/**
 * The PHP type that make:model gives each column, on each driver (spec 4.4).
 * make:model reads the types with ModelProperties::of(). The table kinds has
 * the common types of each dialect. The expected type is the type that a
 * developer expects for the SQL type: an integer type gives int, a floating
 * type float, a boolean type bool, and each other type string (DECIMAL keeps
 * its precision as a string). MySQL and MariaDB keep BOOLEAN as TINYINT(1),
 * so it gives int. SQLite gives string for DOUBLE and BOOLEAN (#50).
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelPropertiesTest extends AbstractDatabaseTestCase
{
    public function testEachColumnGetsThePhpTypeOfItsSqlType(): void
    {
        $this->createTables(KindsMigration::class);

        $this->assertSame($this->expected(), ModelProperties::of($this->adapter(), 'kinds'));
    }

    /**
     * The column and the expected PHP type, in the order of the table, for
     * the dialect of the suite. MariaDB uses the MySQL table.
     *
     * @return array<string, string>
     */
    private function expected(): array
    {
        return match ($this->getDialect()) {
            Dialect::Mysql  => [
                'id'            => 'int',
                'tiny_number'   => 'int',
                'small_number'  => 'int',
                'medium_number' => 'int',
                'big_number'    => 'int',
                'amount'        => 'float',
                'measure'       => 'float',
                'flag'          => 'int',
                'price'         => 'string',
                'label'         => 'string',
                'body'          => 'string',
                'born'          => 'string',
                'created'       => 'string',
                'changed'       => 'string',
                'data'          => 'string',
            ],
            Dialect::Pgsql  => [
                'id'           => 'int',
                'small_number' => 'int',
                'big_number'   => 'int',
                'amount'       => 'float',
                'measure'      => 'float',
                'flag'         => 'bool',
                'price'        => 'string',
                'label'        => 'string',
                'body'         => 'string',
                'born'         => 'string',
                'created'      => 'string',
                'data'         => 'string',
                'code'         => 'string',
            ],
            Dialect::Sqlite => [
                'id'           => 'int',
                'small_number' => 'int',
                'big_number'   => 'int',
                'amount'       => 'float',
                'ratio'        => 'float',
                'measure'      => 'string',
                'flag'         => 'string',
                'price'        => 'string',
                'label'        => 'string',
                'body'         => 'string',
                'born'         => 'string',
                'created'      => 'string',
            ],
        };
    }
}
