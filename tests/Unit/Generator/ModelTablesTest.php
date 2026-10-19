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
use Crest\Generator\ModelNames;
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
    public function testAMapClassCanFailTheSameClassCheck(): void
    {
        $tables = ModelTables::of(
            $this->adapter('mysql', ['members', 'users']),
            [],
            ModelNames::of(['classes' => ['members' => 'Users']])
        );

        $this->assertSame(
            [
                'members' => 'the tables members and users give the same class Users',
                'users'   => 'the tables members and users give the same class Users',
            ],
            $tables->failed
        );
    }

    public function testAMapLineForATableThatTheDatabaseDoesNotHaveGivesANote(): void
    {
        // A view counts as a name of the database. The notes come after the
        // notes of --exclude.
        $tables = ModelTables::of(
            $this->adapter('mysql', ['customers', 'users'], ['customer_names']),
            ['customer_names'],
            ModelNames::of(['classes' => ['Users' => 'Member', 'customer_names' => 'Name', 'gone' => 'Gone']])
        );

        $this->assertSame(
            [
                'Note: customer_names is a view; --all writes no models for views',
                'Note: models.classes names the table Users, which the database does not have; did you mean users?',
                'Note: models.classes names the table gone, which the database does not have',
            ],
            $tables->notes
        );
    }

    public function testAMapLineGivesAClassToANameWithNoClass(): void
    {
        $tables = ModelTables::of(
            $this->adapter('mysql', ['users', 'user-profiles']),
            [],
            ModelNames::of(['classes' => ['user-profiles' => 'UserProfile']])
        );

        $this->assertSame(['user-profiles' => 'UserProfile', 'users' => 'Users'], $tables->classes);
        $this->assertSame([], $tables->failed);
    }

    public function testANameThatGivesNoClassFails(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['users', 'user-profiles']), [], ModelNames::of([]));

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
        $tables = ModelTables::of(
            $this->adapter('sqlite', ['customers', 'sqlite_sequence']),
            ['sqlite_sequence'],
            ModelNames::of([])
        );

        $this->assertSame(['Note: sqlite_sequence is a SQLite table; --all writes no models for it'], $tables->notes);
        $this->assertSame(['customers'], $tables->names());
    }

    public function testAnExcludedTableCausesNoClassFailure(): void
    {
        $tables = ModelTables::of(
            $this->adapter('mysql', ['user_profiles', 'UserProfiles']),
            ['UserProfiles'],
            ModelNames::of([])
        );

        $this->assertSame(['user_profiles' => 'UserProfiles'], $tables->classes);
        $this->assertSame([], $tables->failed);
    }

    public function testAnExcludedTableIsNotInTheList(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['customers', 'invoices']), ['invoices'], ModelNames::of([]));

        $this->assertSame(['customers'], $tables->names());
        $this->assertSame([], $tables->notes);
    }

    public function testAnExcludedViewGivesOneNote(): void
    {
        // A name given two times has no effect.
        $tables = ModelTables::of(
            $this->adapter('mysql', ['customers', 'customer_names'], ['customer_names']),
            ['customer_names', 'customer_names'],
            ModelNames::of([])
        );

        $this->assertSame(['Note: customer_names is a view; --all writes no models for views'], $tables->notes);
        $this->assertSame(['customers'], $tables->names());
    }

    public function testAnInternalSqliteTableIsNotATable(): void
    {
        // SQLite rejects the prefix in any case, so the check ignores case.
        $tables = ModelTables::of(
            $this->adapter('sqlite', ['customers', 'sqlite_sequence', 'SQLITE_STAT1']),
            [],
            ModelNames::of([])
        );

        $this->assertSame(['customers'], $tables->names());
    }

    public function testASqliteNameIsATableOnAServer(): void
    {
        $tables = ModelTables::of($this->adapter('pgsql', ['customers', 'sqlite_cache']), [], ModelNames::of([]));

        $this->assertSame(['customers', 'sqlite_cache'], $tables->names());
    }

    public function testAViewIsNotATable(): void
    {
        $tables = ModelTables::of(
            $this->adapter('mysql', ['customers', 'customer_names'], ['customer_names']),
            [],
            ModelNames::of([])
        );

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

        ModelTables::of($this->adapter('mysql', ['customers', 'Users']), ['USERS', 'nope'], ModelNames::of([]));
    }

    public function testAWrongCaseViewGetsAHint(): void
    {
        // SQLite gives no views in listTables().
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches(
            '/\A--exclude: the database has no table Customer_Names; did you mean customer_names\?\z/'
        );

        ModelTables::of(
            $this->adapter('sqlite', ['customers'], ['customer_names']),
            ['Customer_Names'],
            ModelNames::of([])
        );
    }

    public function testClassesThatDifferOnlyInCaseFail(): void
    {
        // PHP and the file systems of macOS and Windows ignore case. The
        // reason names the class of each table.
        $tables = ModelTables::of($this->adapter('pgsql', ['users', 'USERS', 'uSers']), [], ModelNames::of([]));
        $reason = 'the tables USERS, uSers and users give the classes USERS, USers and Users, '
            . 'which PHP sees as the same class';

        $this->assertSame(['USERS' => $reason, 'uSers' => $reason, 'users' => $reason], $tables->failed);
        $this->assertSame([], $tables->classes);
    }

    public function testTablesWithTheSameClassAfterAnotherTableFail(): void
    {
        // The check reads each class, also after a class with one table.
        $tables = ModelTables::of(
            $this->adapter('mysql', ['customers', 'user_profiles', 'userProfiles']),
            [],
            ModelNames::of([])
        );
        $reason = 'the tables userProfiles and user_profiles give the same class UserProfiles';

        $this->assertSame(['userProfiles' => $reason, 'user_profiles' => $reason], $tables->failed);
        $this->assertSame(['customers' => 'Customers'], $tables->classes);
    }

    public function testTheNamesAreSortedAsStrings(): void
    {
        // SORT_STRING: '10' comes before '9'. The names stay strings, also
        // the numeric names.
        $tables = ModelTables::of($this->adapter('mysql', ['users', '9', '10', 'customers']), [], ModelNames::of([]));

        $this->assertSame(['10', '9', 'customers', 'users'], $tables->names());
    }

    public function testThreeTablesWithTheSameClassAllFail(): void
    {
        $tables = ModelTables::of($this->adapter('mysql', ['a_b', 'aB', 'A_b', 'users']), [], ModelNames::of([]));
        $reason = 'the tables A_b, aB and a_b give the same class AB';

        $this->assertSame(['A_b' => $reason, 'aB' => $reason, 'a_b' => $reason], $tables->failed);
        $this->assertSame(['users' => 'Users'], $tables->classes);
        $this->assertSame(['A_b', 'aB', 'a_b', 'users'], $tables->names());
    }

    public function testUnknownExcludeNamesStopTogether(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/\A--exclude: the database has no table nope, other\z/');

        ModelTables::of(
            $this->adapter('mysql', ['customers']),
            ['nope', 'customers', 'other', 'nope'],
            ModelNames::of([])
        );
    }

    public function testUnlistedSqliteNameIsUnknown(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/\A--exclude: the database has no table sqlite_nope\z/');

        ModelTables::of($this->adapter('sqlite', ['customers']), ['sqlite_nope'], ModelNames::of([]));
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
