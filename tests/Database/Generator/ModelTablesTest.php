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
use Crest\Tests\Support\Migrations\CustomerNamesViewMigration;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\UserProfilesCamelMigration;
use Crest\Tests\Support\Migrations\UserProfilesMigration;

/**
 * The tables of make:model --all on each driver (#52, spec 3.2). MySQL and
 * PostgreSQL give the views in listTables(), and ModelTables removes them.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelTablesTest extends AbstractDatabaseTestCase
{
    public function testAnExcludedViewGivesANote(): void
    {
        $this->createTables(CustomersMigration::class, CustomerNamesViewMigration::class);

        $tables = ModelTables::of($this->adapter(), ['customer_names']);

        $this->assertSame(['Note: customer_names is a view; --all writes no models for views'], $tables->notes);
        $this->assertSame(['customers'], $tables->names());
    }

    public function testAViewIsNotATable(): void
    {
        $this->createTables(CustomersMigration::class, CustomerNamesViewMigration::class);

        $this->assertSame(['customers'], ModelTables::of($this->adapter(), [])->names());
    }

    public function testTablesWithTheSameClassFail(): void
    {
        $this->createTables(UserProfilesMigration::class, UserProfilesCamelMigration::class);

        $tables = ModelTables::of($this->adapter(), []);
        $reason = 'the tables UserProfiles and user_profiles give the same class UserProfiles';

        $this->assertSame(['UserProfiles' => $reason, 'user_profiles' => $reason], $tables->failed);
        $this->assertSame([], $tables->classes);
    }
}
