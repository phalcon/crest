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

use Crest\Generator\ForeignKeys;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\InvoicesMigration;

use function array_values;

/**
 * ForeignKeys::read() reads the keys of each table one time (#52, spec
 * 3.3), on each driver.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ForeignKeysTest extends AbstractDatabaseTestCase
{
    public function testReadGivesTheKeysOfEachTable(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $adapter = $this->adapter();
        $keys    = ForeignKeys::read($adapter);

        $this->assertSame($adapter->listTables(), $keys->tables());
        $this->assertCount(1, $keys->of('invoices'));
        $this->assertEquals(array_values($adapter->describeReferences('invoices')), $keys->of('invoices'));
        $this->assertSame([], $keys->of('customers'));
    }
}
