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
use Crest\Tests\Support\Migrations\CustomersMigration;

/**
 * The internal tables of SQLite and make:model --all (#52, spec 3.2). A
 * table with AUTOINCREMENT makes SQLite create sqlite_sequence, and
 * listTables() gives it.
 *
 * @group sqlite
 */
final class ModelTablesSqliteTest extends AbstractDatabaseTestCase
{
    public function testAnExcludedInternalTableGivesANote(): void
    {
        $this->createTables(CustomersMigration::class);

        $this->assertSame(
            ['Note: sqlite_sequence is a SQLite table; --all writes no models for it'],
            ModelTables::of($this->adapter(), ['sqlite_sequence'])->notes
        );
    }

    public function testAnInternalTableIsNotATable(): void
    {
        $this->createTables(CustomersMigration::class);

        $this->assertContains('sqlite_sequence', $this->adapter()->listTables());
        $this->assertSame(['customers'], ModelTables::of($this->adapter(), [])->names());
    }
}
