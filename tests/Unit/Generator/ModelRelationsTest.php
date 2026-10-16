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
use Crest\Generator\ModelRelations;
use Crest\Tests\Support\Project\CompositeKeySqlite;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Db\Reference;
use PHPUnit\Framework\TestCase;

/**
 * The relations of make:model (#51, spec 3.1 to 3.4). Each test makes its
 * tables in an SQLite database in memory. SQLite gives the keys of a table in
 * the reverse order of the table, so a test that declares recipient_id before
 * sender_id gets them in an order that the sort must change.
 *
 * @phpstan-import-type Relation from ModelRelations
 */
final class ModelRelationsTest extends TestCase
{
    /**
     * The base that the errors about the attributes name.
     */
    private const BASE_FILE = '/app/src/Models/Base/InvoicesBase.php';

    private const CUSTOMERS = 'CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT)';

    private const INVOICE_KEY = 'belongsTo invoices(customer_id) -> customers(id)';

    private const NAMESPACE = 'App\\Models';

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
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, "' . $column . '" INTEGER REFERENCES customers(id))'
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
            $this->relationsOf($adapter, 'invoices', ['id', $column])->relations
        );
    }

    public function testACompositeKeyGivesArraysAndTheClassAsTheAlias(): void
    {
        // SQLite splits a composite key. CompositeKeySqlite gives one
        // reference with two columns, as MySQL does. The first column ends in
        // _id, so an alias from the column would be Order.
        $adapter = new CompositeKeySqlite(
            ['dbname' => ':memory:'],
            [
                'shipments' => [
                    new Reference(
                        'fk_shipments_lines',
                        [
                            'columns'           => ['order_id', 'line_no'],
                            'referencedTable'   => 'lines',
                            'referencedColumns' => ['order_id', 'number'],
                        ]
                    ),
                ],
            ]
        );

        $adapter->execute('CREATE TABLE lines (order_id INTEGER, number INTEGER)');
        $adapter->execute('CREATE TABLE shipments (id INTEGER PRIMARY KEY, order_id INTEGER, line_no INTEGER)');

        $this->assertSame(
            [
                $this->relation(
                    'belongs-to',
                    ['order_id', 'line_no'],
                    '\\App\\Models\\Lines',
                    ['order_id', 'number'],
                    'Lines',
                    'belongsTo shipments(order_id, line_no) -> lines(order_id, number)'
                ),
            ],
            $this->relationsOf($adapter, 'shipments', ['id', 'order_id', 'line_no'])->relations
        );
        $this->assertSame(
            [
                $this->relation(
                    'has-many',
                    ['order_id', 'number'],
                    '\\App\\Models\\Shipments',
                    ['order_id', 'line_no'],
                    'Shipments',
                    'hasMany shipments(order_id, line_no) -> lines(order_id, number)'
                ),
            ],
            $this->relationsOf($adapter, 'lines', ['order_id', 'number'])->relations
        );
    }

    public function testADuplicateAliasSkipsAllItsRelations(): void
    {
        // The belongsTo() alias Customer and the hasMany() alias CUSTOMER are
        // one alias for Phalcon, so crest skips both. billing_id comes after
        // customer_id, so SQLite gives it first: a skipped relation comes
        // before the others, and the count of the aliases must go on.
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id), '
            . 'billing_id INTEGER REFERENCES customers)',
            'CREATE TABLE CUSTOMER (id INTEGER PRIMARY KEY, invoice_id INTEGER REFERENCES invoices(id))'
        );

        $relations = $this->relationsOf($adapter, 'invoices', ['id', 'customer_id', 'billing_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo invoices(billing_id) -> customers(): '
                . 'the key does not name the referenced columns',
                'Skipped the relation ' . self::INVOICE_KEY . ': two or more relations have the alias Customer',
                'Skipped the relation hasMany CUSTOMER(invoice_id) -> invoices(id): '
                . 'two or more relations have the alias CUSTOMER',
            ],
            $relations->skipped
        );
    }

    public function testAHasManyGetsTheClassOfTheOtherTable(): void
    {
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id))'
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

    public function testAKeyWithNoReferencedColumnsIsSkipped(): void
    {
        // SQLite accepts a key with no column, and gives NULL for the column.
        // billing_id comes first, so SQLite gives customer_id first, and the
        // sort of the lines must change the order.
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, billing_id INTEGER REFERENCES customers, '
            . 'customer_id INTEGER REFERENCES customers)'
        );

        $relations = $this->relationsOf($adapter, 'invoices', ['id', 'billing_id', 'customer_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo invoices(billing_id) -> customers(): '
                . 'the key does not name the referenced columns',
                'Skipped the relation belongsTo invoices(customer_id) -> customers(): '
                . 'the key does not name the referenced columns',
            ],
            $relations->skipped
        );
    }

    public function testAnAliasFromAnAttributeReplacesTheRules(): void
    {
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id))'
        );

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
                $adapter,
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
        $adapter = $this->adapter(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE messages (id INTEGER PRIMARY KEY, recipient_id INTEGER REFERENCES users(id), '
            . 'sender_id INTEGER REFERENCES users(id))'
        );

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
                $adapter,
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
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, client_id INTEGER REFERENCES customers(id), '
            . 'customer_id INTEGER REFERENCES customers(id))'
        );

        $relations = $this->relationsOf(
            $adapter,
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
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, total REAL, '
            . 'customer_id INTEGER REFERENCES customers(id))'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            self::BASE_FILE . ", line 16: the alias Total of '" . self::INVOICE_KEY
            . "' has the name of a column; change it"
        );

        $this->relationsOf(
            $adapter,
            'invoices',
            ['id', 'total', 'customer_id'],
            [self::INVOICE_KEY => ['alias' => 'Total', 'line' => 16]]
        );
    }

    public function testAnAliasWithTheNameOfAColumnIsSkipped(): void
    {
        // Phalcon finds an alias without regard to case, so the column
        // CUSTOMER has the name of the alias Customer.
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, CUSTOMER TEXT, '
            . 'customer_id INTEGER REFERENCES customers(id))'
        );

        $relations = $this->relationsOf($adapter, 'invoices', ['id', 'CUSTOMER', 'customer_id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            ['Skipped the relation ' . self::INVOICE_KEY . ': the alias Customer has the name of a column'],
            $relations->skipped
        );
    }

    public function testAnAttributeForAKeyThatIsGoneIsDropped(): void
    {
        // The tables invoices and payments are gone, so crest drops their
        // attributes and says so. The lines are sorted. The attribute of
        // orders comes first and stays, and the check goes on after it.
        $adapter   = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id))'
        );
        $relations = $this->relationsOf(
            $adapter,
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

    public function testAnAttributeForASkippedRelationIsDropped(): void
    {
        // Check 0 comes before the alias, so the attribute does nothing, and
        // crest drops it with a line.
        $adapter   = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers)'
        );
        $relations = $this->relationsOf(
            $adapter,
            'invoices',
            ['id', 'customer_id'],
            ['belongsTo invoices(customer_id) -> customers()' => ['alias' => 'Client', 'line' => 16]]
        );

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation belongsTo invoices(customer_id) -> customers(): '
                . 'the key does not name the referenced columns',
            ],
            $relations->skipped
        );
        $this->assertSame(
            [
                "Dropped the alias Client of 'belongsTo invoices(customer_id) -> customers()': "
                . 'crest skips the relation',
            ],
            $relations->dropped
        );
    }

    public function testAnUnusableTableIsSkipped(): void
    {
        $adapter = $this->adapter(
            'CREATE TABLE users (id INTEGER PRIMARY KEY)',
            'CREATE TABLE "2fa_codes" (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id))'
        );

        $relations = $this->relationsOf($adapter, 'users', ['id']);

        $this->assertSame([], $relations->relations);
        $this->assertSame(
            [
                'Skipped the relation hasMany 2fa_codes(user_id) -> users(id): '
                . "'2fa_codes' gives no usable class name; expected a table name like 'users' or 'user_profiles'",
            ],
            $relations->skipped
        );
    }

    public function testAReservedTableIsSkipped(): void
    {
        $adapter = $this->adapter(
            'CREATE TABLE list (id INTEGER PRIMARY KEY)',
            'CREATE TABLE notes (id INTEGER PRIMARY KEY, list_id INTEGER REFERENCES list(id))'
        );

        $relations = $this->relationsOf($adapter, 'notes', ['id', 'list_id']);

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
        $adapter = $this->adapter(
            'CREATE TABLE employees (id INTEGER PRIMARY KEY, manager_id INTEGER REFERENCES employees(id))'
        );

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
            $this->relationsOf($adapter, 'employees', ['id', 'manager_id'])->relations
        );
    }

    public function testATableWithNoKeysGivesNothing(): void
    {
        $relations = $this->relationsOf($this->adapter(self::CUSTOMERS), 'customers', ['id', 'name']);

        $this->assertSame([], $relations->relations);
        $this->assertSame([], $relations->skipped);
        $this->assertSame([], $relations->dropped);
    }

    public function testIdenticalKeysGiveOneRelation(): void
    {
        // The database accepts two keys with the same columns and the same
        // target. They are one relation, also for the By count of customers.
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id), '
            . 'FOREIGN KEY (customer_id) REFERENCES customers(id))'
        );

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

    public function testTheClassIsInTheNamespaceOfTheModelPath(): void
    {
        // A project with another `model` path, for example Shop\ => app/ and
        // app/Entities.
        $adapter = $this->adapter(
            self::CUSTOMERS,
            'CREATE TABLE invoices (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES customers(id))'
        );

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
                $adapter,
                'invoices',
                'Shop\\Entities',
                ['id', 'customer_id'],
                [],
                self::BASE_FILE
            )->relations
        );
    }

    public function testTwoAliasesFromAttributesThatAreTheSameStop(): void
    {
        // SQLite gives sender_id first, so crest finds line 17 first. The
        // message gives the two lines in order.
        $adapter = $this->adapter(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE messages (id INTEGER PRIMARY KEY, recipient_id INTEGER REFERENCES users(id), '
            . 'sender_id INTEGER REFERENCES users(id))'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            self::BASE_FILE . ', lines 16 and 17: two attributes have the alias Users; change one of them'
        );

        $this->relationsOf(
            $adapter,
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
        $adapter = $this->adapter(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE messages (id INTEGER PRIMARY KEY, recipient_id INTEGER REFERENCES users(id), '
            . 'sender_id INTEGER REFERENCES users(id))'
        );

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

    /**
     * An SQLite database in memory, with the statements run on it.
     */
    private function adapter(string ...$statements): Sqlite
    {
        $adapter = new Sqlite(['dbname' => ':memory:']);

        foreach ($statements as $statement) {
            $adapter->execute($statement);
        }

        return $adapter;
    }

    /**
     * @param list<string> $fields
     * @param list<string> $referenced
     *
     * @return Relation
     */
    private function relation(
        string $kind,
        array $fields,
        string $class,
        array $referenced,
        string $alias,
        string $key
    ): array {
        return [
            'kind'       => $kind,
            'fields'     => $fields,
            'class'      => $class,
            'referenced' => $referenced,
            'alias'      => $alias,
            'key'        => $key,
        ];
    }

    /**
     * The relations of the table in the namespace App\Models, with the
     * attributes of $stored in the base self::BASE_FILE.
     *
     * @param list<string>                                   $columns
     * @param array<string, array{alias: string, line: int}> $stored
     */
    private function relationsOf(
        AdapterInterface $adapter,
        string $table,
        array $columns,
        array $stored = []
    ): ModelRelations {
        return ModelRelations::of($adapter, $table, self::NAMESPACE, $columns, $stored, self::BASE_FILE);
    }
}
