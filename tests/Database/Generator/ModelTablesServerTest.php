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

use Crest\Generator\ModelTables;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\SqliteCacheMigration;

/**
 * On MySQL and PostgreSQL, a name with the prefix sqlite_ is a usual table
 * (#52, spec 3.2). SQLite rejects the name, so this test does not run there.
 *
 * @group mysql
 * @group pgsql
 */
final class ModelTablesServerTest extends AbstractDatabaseTestCase
{
    public function testASqliteNameIsATable(): void
    {
        $this->createTables(SqliteCacheMigration::class);

        $this->assertSame(['sqlite_cache'], ModelTables::of($this->adapter(), [])->names());
    }
}
