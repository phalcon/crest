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

use Crest\Console\Exceptions\Exception;
use Crest\Generator\ForeignKeys;
use Crest\Generator\ModelNames;
use Crest\Generator\ModelRelations;
use Crest\Tests\Support\Migrations\CustomerInCapitalsMigration;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\EmployeesMigration;
use Crest\Tests\Support\Migrations\InvoicesKeyColumnMigration;
use Crest\Tests\Support\Migrations\InvoicesMigration;
use Crest\Tests\Support\Migrations\InvoicesWithClientMigration;
use Crest\Tests\Support\Migrations\InvoicesWithCustomerColumnMigration;
use Crest\Tests\Support\Migrations\InvoicesWithIdenticalKeysMigration;
use Crest\Tests\Support\Migrations\ListMigration;
use Crest\Tests\Support\Migrations\MessagesMigration;
use Crest\Tests\Support\Migrations\NotesMigration;
use Crest\Tests\Support\Migrations\OrdersMigration;
use Crest\Tests\Support\Migrations\TwoFactorCodesMigration;
use Crest\Tests\Support\Migrations\UsersMigration;

/**
 * The relations of make:model (#51, spec 3.1 to 3.4), on each driver. The
 * tests that need SQLite are in ModelRelationsSqliteTest, and the composite
 * key of MySQL and PostgreSQL is in ModelRelationsCompositeKeyTest. SQLite
 * gives the keys of a table in the reverse order of the table, so a test that
 * declares recipient_id before sender_id gets them in an order that the sort
 * must change.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelRelationsTest extends AbstractModelRelationsTestCase
{
    /**
     * The key column of invoices, and the alias of its belongsTo().
     *
     * @return iterable<string, array{string, string}>
     */
    public static function keyColumns(): iterable
    {
        yield 'a column with _id' => ['customer_id', 'Customer'];
        yield 'the _id in another case' => ['customer_ID', 'Customer'];
        yield 'a column with two parts' => ['billing_address_id', 'BillingAddress'];
        yield 'a column with no _id' => ['customer', 'Customers'];
        yield 'only _id' => ['_id', 'Customers'];
        yield 'a column that gives no name' => ['_2nd_id', 'Customers'];
        yield 'a reserved word' => ['list_id', 'List'];
    }

    /**
     * @dataProvider keyColumns
     */
    public function testABelongsToGetsItsAliasFromTheKeyColumn(string $column, string $alias): void
    {
        $this->createTables(
            CustomersMigration::class,
            $this->migration(InvoicesKeyColumnMigration::class)->column($column)
        );

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    [$column],
                    '\\App\\Models\\Customers',
                    ['id'],
                    $alias,
                    'belongsTo invoices(' . $column . ') -> customers(id)'
                ),
            ],
            $this->relationsOf($this->adapter(), 'invoices', ['id', $column])->relations
        );
    }

    public function testADuplicateAliasSkipsAllItsRelations(): void
    {
        // The belongsTo() alias Customer and the hasMany() alias CUSTOMER are
        // one alias for Phalcon, so crest skips both. ModelRelationsSqliteTest
        // has this test with a key that SQLite skips first.
        $this->createTables(
            CustomersMigration::class,
            InvoicesMigration::class,
            CustomerInCapitalsMigration::class
        );

        $relations = $this->relationsOf($this->adapter(), 'invoices', ['id', 'customer_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation ' . self::INVOICE_KEY . ': two or more relations have the alias Customer',
                'Skipped the relation hasMany CUSTOMER(invoice_id) -> invoices(id): '
                . 'two or more relations have the alias CUSTOMER',
            ],
            $relations->skipped
        );
    }

    public function testAHasManyGetsTheClassOfTheOtherTable(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Invoices',
                    ['customer_id'],
                    'Invoices',
                    'hasMany invoices(customer_id) -> customers(id)'
                ),
            ],
            $this->relationsOf($this->adapter(), 'customers', ['id', 'name'])->relations
        );
    }

    public function testAHasManyUsesThePropertyNamesOfBothTables(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $names = ModelNames::of(['camelCase' => true])
            ->withStored('customers', ['id' => ['name' => 'code', 'line' => 6]], '/app/CustomersBase.php');

        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['code'],
                    '\\App\\Models\\Invoices',
                    ['customerId'],
                    'Invoices',
                    'hasMany invoices(customer_id) -> customers(id)'
                ),
            ],
            $this->relationsOf($this->adapter(), 'customers', ['code', 'name'], [], $names)->relations
        );
    }

    public function testAnAliasFromAnAttributeReplacesTheRules(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['customer_id'],
                    '\\App\\Models\\Customers',
                    ['id'],
                    'Client',
                    self::INVOICE_KEY
                ),
            ],
            $this->relationsOf(
                $this->adapter(),
                'invoices',
                ['id', 'customer_id'],
                [self::INVOICE_KEY => ['alias' => 'Client', 'line' => 16]]
            )->relations
        );
    }

    public function testAnAliasFromAnAttributeStaysWhenASecondKeyComes(): void
    {
        // An earlier run gave sender_id the alias Messages. Now messages has
        // a second key to users, and only the new key gets an alias with By.
        $this->createTables(UsersMigration::class, MessagesMigration::class);

        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Messages',
                    ['sender_id'],
                    'Messages',
                    'hasMany messages(sender_id) -> users(id)'
                ),
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Messages',
                    ['recipient_id'],
                    'MessagesByRecipient',
                    'hasMany messages(recipient_id) -> users(id)'
                ),
            ],
            $this->relationsOf(
                $this->adapter(),
                'users',
                ['id', 'name'],
                ['hasMany messages(sender_id) -> users(id)' => ['alias' => 'Messages', 'line' => 16]]
            )->relations
        );
    }

    public function testAnAliasFromAnAttributeWinsOverANewAlias(): void
    {
        // The developer gave client_id the alias Customer. The key
        // customer_id gets Customer from the rules, so crest skips only it.
        $this->createTables(CustomersMigration::class, InvoicesWithClientMigration::class);

        $relations = $this->relationsOf(
            $this->adapter(),
            'invoices',
            ['id', 'client_id', 'customer_id'],
            ['belongsTo invoices(client_id) -> customers(id)' => ['alias' => 'Customer', 'line' => 16]]
        );

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['client_id'],
                    '\\App\\Models\\Customers',
                    ['id'],
                    'Customer',
                    'belongsTo invoices(client_id) -> customers(id)'
                ),
            ],
            $relations->relations
        );
        $this->assertSame(
            ['Skipped the relation ' . self::INVOICE_KEY . ': two or more relations have the alias Customer'],
            $relations->skipped
        );
    }

    public function testAnAliasFromAnAttributeWithTheNameOfAColumnStops(): void
    {
        // A skip would remove the relation from the base, so crest stops,
        // with the file and the line of the attribute. The check ignores
        // case: Total is the column total.
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            self::BASE_FILE . ", line 16: the alias Total of '" . self::INVOICE_KEY
            . "' has the name of a property; change it"
        );

        $this->relationsOf(
            $this->adapter(),
            'invoices',
            ['id', 'total', 'customer_id'],
            [self::INVOICE_KEY => ['alias' => 'Total', 'line' => 16]]
        );
    }

    public function testAnAliasWithTheNameOfAColumnIsSkipped(): void
    {
        // Phalcon finds an alias without regard to case, so the column
        // CUSTOMER has the name of the alias Customer.
        $this->createTables(
            CustomersMigration::class,
            $this->migration(InvoicesWithCustomerColumnMigration::class)->column('CUSTOMER')
        );

        $relations = $this->relationsOf($this->adapter(), 'invoices', ['id', 'CUSTOMER', 'customer_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            ['Skipped the relation ' . self::INVOICE_KEY . ': the alias Customer has the name of a property'],
            $relations->skipped
        );
    }

    public function testAnAttributeForAKeyThatIsGoneIsDropped(): void
    {
        // The tables invoices and payments are gone, so crest drops their
        // attributes and says so. The lines are sorted. The attribute of
        // orders comes first and stays, and the check goes on after it.
        $this->createTables(CustomersMigration::class, OrdersMigration::class);

        $relations = $this->relationsOf(
            $this->adapter(),
            'customers',
            ['id', 'name'],
            [
                'hasMany orders(customer_id) -> customers(id)'   => ['alias' => 'Orders', 'line' => 16],
                'hasMany payments(customer_id) -> customers(id)' => ['alias' => 'Payments', 'line' => 17],
                'hasMany invoices(customer_id) -> customers(id)' => ['alias' => 'Invoices', 'line' => 18],
            ]
        );

        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Orders',
                    ['customer_id'],
                    'Orders',
                    'hasMany orders(customer_id) -> customers(id)'
                ),
            ],
            $relations->relations
        );
        $this->assertSame([], $relations->skipped);
        $this->assertSame(
            [
                "Dropped the alias Invoices of 'hasMany invoices(customer_id) -> customers(id)': "
                . 'the database has no such key',
                "Dropped the alias Payments of 'hasMany payments(customer_id) -> customers(id)': "
                . 'the database has no such key',
            ],
            $relations->dropped
        );
    }

    public function testAnUnusableTableIsSkipped(): void
    {
        $this->createTables(UsersMigration::class, TwoFactorCodesMigration::class);

        $relations = $this->relationsOf($this->adapter(), 'users', ['id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation hasMany 2fa_codes(user_id) -> users(id): '
                . "'2fa_codes' gives no usable class name; expected a table name like 'users' or 'user_profiles'",
            ],
            $relations->skipped
        );
    }

    public function testARelatedBaseThatCannotBeReadSkipsTheRelation(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $relations = $this->relationsOf(
            $this->adapter(),
            'invoices',
            ['id', 'customer_id', 'total'],
            [],
            ModelNames::of([])->withFailed('customers', 'the reason')
        );

        $this->assertSame([], $relations->relations);
        $this->assertSame(['Skipped the relation ' . self::INVOICE_KEY . ': the reason'], $relations->skipped);
    }

    public function testARelatedColumnWithNoUsablePropertySkipsTheRelation(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $relations = $this->relationsOf(
            $this->adapter(),
            'invoices',
            ['id', 'customer_id', 'total'],
            [],
            ModelNames::of([])->withStored('customers', ['id' => ['name' => '2nd', 'line' => 6]], '/b.php')
        );

        $this->assertSame(
            [
                'Skipped the relation ' . self::INVOICE_KEY
                . ": column 'id' of table 'customers' has no usable property name",
            ],
            $relations->skipped
        );
    }

    public function testAReservedTableIsSkipped(): void
    {
        $this->createTables(ListMigration::class, NotesMigration::class);

        $relations = $this->relationsOf($this->adapter(), 'notes', ['id', 'list_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo notes(list_id) -> list(id): '
                . "'list' gives the class List, but PHP reserves that name",
            ],
            $relations->skipped
        );
    }

    public function testASelfReferenceGivesBothRelations(): void
    {
        // The belongsTo() comes first, although its alias sorts after the
        // alias of the hasMany(). The two keys differ only in the method.
        $this->createTables(EmployeesMigration::class);

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['manager_id'],
                    '\\App\\Models\\Employees',
                    ['id'],
                    'Manager',
                    'belongsTo employees(manager_id) -> employees(id)'
                ),
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Employees',
                    ['manager_id'],
                    'Employees',
                    'hasMany employees(manager_id) -> employees(id)'
                ),
            ],
            $this->relationsOf($this->adapter(), 'employees', ['id', 'manager_id'])->relations
        );
    }

    public function testATableWithNoKeysGivesNothing(): void
    {
        $this->createTables(CustomersMigration::class);

        $relations = $this->relationsOf($this->adapter(), 'customers', ['id', 'name']);

        $this->assertSame([], $relations->relations);
        $this->assertSame([], $relations->skipped);
        $this->assertSame([], $relations->dropped);
    }

    public function testIdenticalKeysGiveOneRelation(): void
    {
        // The database accepts two keys with the same columns and the same
        // target. They are one relation, also for the By count of customers.
        $this->createTables(CustomersMigration::class, InvoicesWithIdenticalKeysMigration::class);

        $adapter = $this->adapter();

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['customer_id'],
                    '\\App\\Models\\Customers',
                    ['id'],
                    'Customer',
                    self::INVOICE_KEY
                ),
            ],
            $this->relationsOf($adapter, 'invoices', ['id', 'customer_id'])->relations
        );
        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Invoices',
                    ['customer_id'],
                    'Invoices',
                    'hasMany invoices(customer_id) -> customers(id)'
                ),
            ],
            $this->relationsOf($adapter, 'customers', ['id', 'name'])->relations
        );
    }

    public function testTheClassComesFromTheMap(): void
    {
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['customer_id'],
                    '\\App\\Models\\Customer',
                    ['id'],
                    'Customer',
                    self::INVOICE_KEY
                ),
            ],
            $this->relationsOf(
                $this->adapter(),
                'invoices',
                ['id', 'customer_id', 'total'],
                [],
                ModelNames::of(['classes' => ['customers' => 'Customer']])
            )->relations
        );
    }

    public function testTheClassIsInTheNamespaceOfTheModelPath(): void
    {
        // A project with another `model` path, for example Shop\ => app/ and
        // app/Entities.
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['customer_id'],
                    '\\Shop\\Entities\\Customers',
                    ['id'],
                    'Customer',
                    self::INVOICE_KEY
                ),
            ],
            ModelRelations::of(
                ForeignKeys::read($this->adapter()),
                ModelNames::of([]),
                'invoices',
                'Shop\\Entities',
                ['id', 'customer_id'],
                [],
                self::BASE_FILE
            )->relations
        );
    }

    public function testTheFieldsAreThePropertyNamesOfBothTables(): void
    {
        // camelCase gives customerId; the attribute of the base of customers
        // gives code for its id. The key keeps the column names.
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $names = ModelNames::of(['camelCase' => true])
            ->withStored('customers', ['id' => ['name' => 'code', 'line' => 6]], '/app/CustomersBase.php');

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['customerId'],
                    '\\App\\Models\\Customers',
                    ['code'],
                    'Customer',
                    self::INVOICE_KEY
                ),
            ],
            $this->relationsOf($this->adapter(), 'invoices', ['id', 'customerId', 'total'], [], $names)->relations
        );
    }

    public function testTwoAliasesFromAttributesThatAreTheSameStop(): void
    {
        // SQLite gives sender_id first, so crest finds line 17 first. The
        // message gives the two lines in order.
        $this->createTables(UsersMigration::class, MessagesMigration::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            self::BASE_FILE . ', lines 16 and 17: two attributes have the alias Users; change one of them'
        );

        $this->relationsOf(
            $this->adapter(),
            'messages',
            ['id', 'recipient_id', 'sender_id'],
            [
                'belongsTo messages(recipient_id) -> users(id)' => ['alias' => 'Users', 'line' => 16],
                'belongsTo messages(sender_id) -> users(id)'    => ['alias' => 'Users', 'line' => 17],
            ]
        );
    }

    public function testTwoKeysToOneTableAddByToTheAliases(): void
    {
        // recipient_id comes first, so SQLite gives sender_id first.
        $this->createTables(UsersMigration::class, MessagesMigration::class);

        $adapter = $this->adapter();

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['recipient_id'],
                    '\\App\\Models\\Users',
                    ['id'],
                    'Recipient',
                    'belongsTo messages(recipient_id) -> users(id)'
                ),
                $this->relation(
                    'belongs-to',
                    ['sender_id'],
                    '\\App\\Models\\Users',
                    ['id'],
                    'Sender',
                    'belongsTo messages(sender_id) -> users(id)'
                ),
            ],
            $this->relationsOf($adapter, 'messages', ['id', 'recipient_id', 'sender_id'])->relations
        );
        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Messages',
                    ['recipient_id'],
                    'MessagesByRecipient',
                    'hasMany messages(recipient_id) -> users(id)'
                ),
                $this->relation(
                    'has-many',
                    ['id'],
                    '\\App\\Models\\Messages',
                    ['sender_id'],
                    'MessagesBySender',
                    'hasMany messages(sender_id) -> users(id)'
                ),
            ],
            $this->relationsOf($adapter, 'users', ['id', 'name'])->relations
        );
    }
}
