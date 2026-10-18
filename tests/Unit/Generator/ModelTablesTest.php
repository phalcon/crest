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

use Crest\Console\Exceptions\Exception;
use Crest\Generator\ModelTables;
use Phalcon\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

/**
 * The rules of the tables of make:model --all (#52, spec 3.2), with a stub
 * adapter. The stub gives names that a driver cannot give in the suite: the
 * names of another driver, and names that SQLite rejects. The tests in
 * tests/Database show what each driver gives.
 */
final class ModelTablesTest extends TestCase
{
    public function testANameThatGivesNoClassFails(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['users', 'user-profiles']), []);

        $this->assertSame(
            ['user-profiles' => "'user-profiles' gives no usable class name; "
                . "expected a table name like 'users' or 'user_profiles'"],
            $tables->failed
        );
        $this->assertSame(['users' => 'Users'], $tables->classes);
        $this->assertSame(['user-profiles', 'users'], $tables->names());
    }

    public function testAnExcludedInternalSqliteTableGivesANote(): void
    {
        $tables = ModelTables::of($this->adapter('sqlite', ['customers', 'sqlite_sequence']), ['sqlite_sequence']);

        $this->assertSame(['Note: sqlite_sequence is a SQLite table; --all writes no models for it'], $tables->notes);
        $this->assertSame(['customers'], $tables->names());
    }

    public function testAnExcludedTableCausesNoClassFailure(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['user_profiles', 'UserProfiles']), ['UserProfiles']);

        $this->assertSame(['user_profiles' => 'UserProfiles'], $tables->classes);
        $this->assertSame([], $tables->failed);
    }

    public function testAnExcludedTableIsNotInTheList(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['customers', 'invoices']), ['invoices']);

        $this->assertSame(['customers'], $tables->names());
        $this->assertSame([], $tables->notes);
    }

    public function testAnExcludedViewGivesOneNote(): void
    {
        // A name given two times has no effect.
        $tables = ModelTables::of(
            $this->adapter('mysql', ['customers', 'customer_names'], ['customer_names']),
            ['customer_names', 'customer_names']
        );

        $this->assertSame(['Note: customer_names is a view; --all writes no models for views'], $tables->notes);
        $this->assertSame(['customers'], $tables->names());
    }

    public function testAnInternalSqliteTableIsNotATable(): void
    {
        // SQLite rejects the prefix in any case, so the check ignores case.
        $tables = ModelTables::of($this->adapter('sqlite', ['customers', 'sqlite_sequence', 'SQLITE_STAT1']), []);

        $this->assertSame(['customers'], $tables->names());
    }

    public function testASqliteNameIsATableOnAServer(): void
    {
        $tables = ModelTables::of($this->adapter('pgsql', ['customers', 'sqlite_cache']), []);

        $this->assertSame(['customers', 'sqlite_cache'], $tables->names());
    }

    public function testAViewIsNotATable(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['customers', 'customer_names'], ['customer_names']), []);

        $this->assertSame(['customers'], $tables->names());
        $this->assertSame(['customers' => 'Customers'], $tables->classes);
    }

    public function testAWrongCaseNameGetsAHint(): void
    {
        // MySQL on macOS and Windows, and PostgreSQL for a name with no
        // quotes, keep a name in another case.
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches(
            '/\A--exclude: the database has no table USERS, nope; did you mean Users\?\z/'
        );

        ModelTables::of($this->adapter('mysql', ['customers', 'Users']), ['USERS', 'nope']);
    }

    public function testAWrongCaseViewGetsAHint(): void
    {
        // SQLite gives no views in listTables().
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches(
            '/\A--exclude: the database has no table Customer_Names; did you mean customer_names\?\z/'
        );

        ModelTables::of($this->adapter('sqlite', ['customers'], ['customer_names']), ['Customer_Names']);
    }

    public function testClassesThatDifferOnlyInCaseFail(): void
    {
        // PHP and the file systems of macOS and Windows ignore case. The
        // reason names the class of each table.
        $tables = ModelTables::of($this->adapter('pgsql', ['users', 'USERS', 'uSers']), []);
        $reason = 'the tables USERS, uSers and users give the classes USERS, USers and Users, '
            . 'which PHP sees as the same class';

        $this->assertSame(['USERS' => $reason, 'uSers' => $reason, 'users' => $reason], $tables->failed);
        $this->assertSame([], $tables->classes);
    }

    public function testTablesWithTheSameClassAfterAnotherTableFail(): void
    {
        // The check reads each class, also after a class with one table.
        $tables = ModelTables::of($this->adapter('mysql', ['customers', 'user_profiles', 'userProfiles']), []);
        $reason = 'the tables userProfiles and user_profiles give the same class UserProfiles';

        $this->assertSame(['userProfiles' => $reason, 'user_profiles' => $reason], $tables->failed);
        $this->assertSame(['customers' => 'Customers'], $tables->classes);
    }

    public function testTheNamesAreSortedAsStrings(): void
    {
        // SORT_STRING: '10' comes before '9'. The names stay strings, also
        // the numeric names.
        $tables = ModelTables::of($this->adapter('mysql', ['users', '9', '10', 'customers']), []);

        $this->assertSame(['10', '9', 'customers', 'users'], $tables->names());
    }

    public function testThreeTablesWithTheSameClassAllFail(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['a_b', 'aB', 'A_b', 'users']), []);
        $reason = 'the tables A_b, aB and a_b give the same class AB';

        $this->assertSame(['A_b' => $reason, 'aB' => $reason, 'a_b' => $reason], $tables->failed);
        $this->assertSame(['users' => 'Users'], $tables->classes);
        $this->assertSame(['A_b', 'aB', 'a_b', 'users'], $tables->names());
    }

    public function testUnknownExcludeNamesStopTogether(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/\A--exclude: the database has no table nope, other\z/');

        ModelTables::of($this->adapter('mysql', ['customers']), ['nope', 'customers', 'other', 'nope']);
    }

    public function testUnlistedSqliteNameIsUnknown(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/\A--exclude: the database has no table sqlite_nope\z/');

        ModelTables::of($this->adapter('sqlite', ['customers']), ['sqlite_nope']);
    }

    /**
     * An adapter that gives only the type, the tables and the views.
     *
     * @param list<string> $tables
     * @param list<string> $views
     */
    private function adapter(string $type, array $tables, array $views = []): AdapterInterface
    {
        return $this->createConfiguredStub(
            AdapterInterface::class,
            ['getType' => $type, 'listTables' => $tables, 'listViews' => $views]
        );
    }
}
