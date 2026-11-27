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

use Crest\Generator\ModelNames;
use Crest\Generator\ModelTables;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\MeasuresPartitionsMigration;

/**
 * A partitioned table on PostgreSQL gives one model for --all, and its
 * partitions give none (#101).
 *
 * @group pgsql
 */
final class ModelTablesPartitionTest extends AbstractDatabaseTestCase
{
    public function testAPartitionIsNotATable(): void
    {
        $this->skipListTablesOnOldCphalcon();
        $this->createTables(MeasuresPartitionsMigration::class);

        $this->assertSame(['measures'], ModelTables::of($this->adapter(), [], ModelNames::of([]))->names());
    }
}
