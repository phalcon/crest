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
use Phalcon\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

/**
 * The names of make:model (#53, spec 3.1 and 3.2): the models key of
 * crest.php, the class of a table, and the property of a column.
 */
final class ModelNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badModelsKeys(): iterable
    {
        yield 'not an array' => ['yes', 'crest.php: models must be an array'];
        yield 'an unknown key' => [
            ['camelcase' => true],
            "crest.php: models has an unknown key 'camelcase'; the keys are classes, camelCase, getSet and schema",
        ];
        yield 'a list' => [
            [true],
            "crest.php: models has an unknown key '0'; the keys are classes, camelCase, getSet and schema",
        ];
        yield 'classes that are not an array' => [
            ['classes' => 'users'],
            'crest.php: models.classes must be an array of table => class',
        ];
        yield 'a class that is not a string' => [
            ['classes' => ['users' => 1]],
            "crest.php: models.classes: the class of 'users' must be a string",
        ];
        yield 'a class that is not a name' => [
            ['classes' => ['user-profiles' => 'User Profile']],
            "crest.php: models.classes: 'user-profiles' gives 'User Profile', which is not a usable class name",
        ];
        yield 'a reserved class' => [
            ['classes' => ['lists' => 'LIST']],
            "crest.php: models.classes: 'lists' gives the class LIST, but PHP reserves that name",
        ];
        yield 'two tables, one class' => [
            ['classes' => ['users' => 'User', 'accounts' => 'User']],
            'crest.php: models.classes: the tables accounts and users give the same class User',
        ];
        yield 'two tables, one class, after a line with its own class' => [
            ['classes' => ['orders' => 'Order', 'users' => 'User', 'accounts' => 'User']],
            'crest.php: models.classes: the tables accounts and users give the same class User',
        ];
        yield 'two tables, one class in two cases' => [
            ['classes' => ['users' => 'User', 'members' => 'USER']],
            'crest.php: models.classes: the tables members and users give the classes USER and User, '
            . 'which PHP sees as the same class',
        ];
        yield 'camelCase that is not a boolean' => [
            ['camelCase' => 'yes'],
            'crest.php: models.camelCase must be true or false',
        ];
        yield 'getSet that is not a boolean' => [
            ['getSet' => 'yes'],
            'crest.php: models.getSet must be true or false',
        ];

        $message = 'crest.php: models.schema must be the name of a schema';

        yield 'a schema that is not a string' => [['schema' => 1], $message];
        yield 'an empty schema' => [['schema' => ''], $message];
        yield 'a schema of spaces' => [['schema' => '  '], $message];
        yield 'a schema for each table' => [['schema' => ['invoices' => 'billing']], $message];
    }

    /**
     * @return iterable<string, array{ModelNames, list<string>, string}>
     */
    public static function badNames(): iterable
    {
        $model = 'has the name of a property of Phalcon\\Mvc\\Model';

        yield 'a column that is not a name' => [
            ModelNames::of([]),
            ['id', 'first name'],
            "column 'first name' of table 't' is not a usable property name; "
            . 'give it one with --property="first name=<name>"',
        ];
        yield 'a column with the name of a model property' => [
            ModelNames::of([]),
            ['container'],
            "column 'container' of table 't' " . $model . '; give it another name with --property="container=<name>"',
        ];
        yield 'a camelCase name that is not a name' => [
            ModelNames::of(['camelCase' => true]),
            ['_1x'],
            "column '_1x' of table 't' gives '1x' with camelCase, which is not a usable property name; "
            . 'give it one with --property="_1x=<name>"',
        ];
        yield 'a camelCase name of a model property' => [
            ModelNames::of(['camelCase' => true]),
            ['container_'],
            "column 'container_' of table 't' gives 'container' with camelCase, which " . $model
            . '; give it another name with --property="container_=<name>"',
        ];
        yield 'a given name that is not a name' => [
            ModelNames::of([])->withGiven('t', ['id' => 'first-name']),
            ['id'],
            "--property: 'id' gives 'first-name', which is not a usable property name",
        ];
        yield 'a given name of a model property' => [
            ModelNames::of([])->withGiven('t', ['box' => 'container']),
            ['box'],
            "--property: 'box' gives 'container', which " . $model,
        ];
        yield 'a stored name that is not a name' => [
            ModelNames::of([])->withStored('t', ['id' => ['name' => 'first-name', 'line' => 9]], '/b.php'),
            ['id'],
            "/b.php, line 9: the property name 'first-name' is not usable; change it",
        ];
        yield 'a stored name of a model property' => [
            ModelNames::of([])->withStored('t', ['id' => ['name' => 'container', 'line' => 9]], '/b.php'),
            ['id'],
            "/b.php, line 9: the property name 'container' " . $model . '; change it',
        ];
        yield 'two columns, one name' => [
            ModelNames::of(['camelCase' => true]),
            ['first_name', 'firstName'],
            "columns 'first_name' and 'firstName' of table 't' give the same property firstName; "
            . 'give one of them another name with --property',
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function camelCaseNames(): iterable
    {
        yield 'snake case' => ['first_name', 'firstName'];
        yield 'camel case' => ['firstName', 'firstName'];
        yield 'one word' => ['name', 'name'];
        yield 'capitals' => ['ID', 'iD'];
        yield 'capitals with an underscore' => ['USER_ID', 'uSERID'];
        yield 'two underscores' => ['a__b', 'aB'];
        yield 'a first underscore' => ['_id', 'id'];
    }

    /**
     * Conflict a of #70 (spec 3.4): a getter or a setter that Phalcon\Mvc\Model
     * has, for each source of the name.
     *
     * @return iterable<string, array{ModelNames, list<string>, string}>
     */
    public static function modelMethods(): iterable
    {
        $model  = 'which Phalcon\\Mvc\\Model has';
        $getSet = ModelNames::of(['getSet' => true]);

        yield 'the column' => [
            $getSet,
            ['id', 'source'],
            "column 'source' of table 'pages' gives the method getSource(), " . $model
            . '; give it another name with --property="source=<name>"',
        ];
        yield 'the column with camelCase' => [
            ModelNames::of(['camelCase' => true, 'getSet' => true]),
            ['write_connection'],
            "column 'write_connection' of table 'pages' gives the method getWriteConnection(), " . $model
            . '; give it another name with --property="write_connection=<name>"',
        ];
        yield 'a setter only' => [
            $getSet,
            ['connection_service'],
            "column 'connection_service' of table 'pages' gives the method setConnectionService(), " . $model
            . '; give it another name with --property="connection_service=<name>"',
        ];
        yield 'the property option' => [
            $getSet->withGiven('pages', ['title' => 'source']),
            ['title'],
            "--property: 'source' gives the method getSource(), " . $model,
        ];
        yield 'an attribute' => [
            $getSet->withStored('pages', ['title' => ['name' => 'messages', 'line' => 7]], '/b.php'),
            ['title'],
            "/b.php, line 7: the property name 'messages' gives the method getMessages(), " . $model . '; change it',
        ];
    }

    /**
     * Conflict b of #70 (spec 3.4), for each source of the name. PHP method
     * names are not case-sensitive.
     *
     * @return iterable<string, array{ModelNames, list<string>, string}>
     */
    public static function twoPropertiesOneMethod(): iterable
    {
        $message = "columns 'first_name' and '%s' of table 'people' give the same methods getFirstName() "
            . 'and setFirstName(); give one of them another name with --property';
        $getSet  = ModelNames::of(['getSet' => true]);

        yield 'the same name' => [$getSet, ['first_name', 'firstName'], sprintf($message, 'firstName')];
        yield 'case only' => [$getSet, ['first_name', 'firstname'], sprintf($message, 'firstname')];
        yield 'the property option' => [
            $getSet->withGiven('people', ['fname' => 'firstName']),
            ['first_name', 'fname'],
            sprintf($message, 'fname'),
        ];
        yield 'an attribute' => [
            $getSet->withStored('people', ['fname' => ['name' => 'firstName', 'line' => 7]], '/b.php'),
            ['first_name', 'fname'],
            sprintf($message, 'fname'),
        ];
    }

    /**
     * @dataProvider badModelsKeys
     */
    public function testABadModelsKeyStops(mixed $models, string $message): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        ModelNames::of($models);
    }

    /**
     * @param list<string> $columns
     *
     * @dataProvider badNames
     */
    public function testABadNameStopsWithItsSource(ModelNames $names, array $columns, string $message): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $names->properties('t', $columns);
    }

    public function testAFailedTableStopsWithItsReason(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('the reason');

        ModelNames::of([])->withFailed('customers', 'the reason')->propertyOf('customers', 'id');
    }

    public function testAGivenColumnThatTheTableDoesNotHaveStops(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("--property: table 'users' has no column 'Name'; did you mean name?");

        ModelNames::of([])->withGiven('users', ['Name' => 'fullName'])->properties('users', ['id', 'name']);
    }

    public function testAGivenColumnWithNoMatchHasNoHint(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/\\A--property: table 'users' has no column 'nope'\\z/");

        ModelNames::of([])->withGiven('users', ['nope' => 'x'])->properties('users', ['id', 'name']);
    }

    public function testAGivenNameAppliesOnlyToItsTable(): void
    {
        $names = ModelNames::of([])->withGiven('users', ['id' => 'code']);

        $this->assertSame('code', $names->propertyOf('users', 'id'));
        $this->assertSame('id', $names->propertyOf('orders', 'id'));
    }

    public function testAHintGivesTheColumnWithItsCase(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("--property: table 'users' has no column 'firstname'; did you mean FirstName?");

        ModelNames::of([])->withGiven('users', ['firstname' => 'x'])->properties('users', ['id', 'FirstName']);
    }

    public function testAMapLineGivesAClassToANameWithNoClass(): void
    {
        $names = ModelNames::of(['classes' => ['user-profiles' => 'UserProfile', 'list' => 'Listing']]);

        $this->assertSame('UserProfile', $names->classOf('user-profiles'));
        $this->assertSame('Listing', $names->classOf('list'));
    }

    public function testAMapLineWins(): void
    {
        $names = ModelNames::of(['classes' => ['users' => 'Member']]);

        $this->assertSame('Member', $names->classOf('users'));
        $this->assertSame('Orders', $names->classOf('orders'));
    }

    /**
     * @param list<string> $columns
     *
     * @dataProvider modelMethods
     */
    public function testAMethodOfTheModelStops(ModelNames $names, array $columns, string $message): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $names->properties('pages', $columns);
    }

    public function testANullSchemaIsNoSchema(): void
    {
        $names = ModelNames::of(['schema' => null]);

        $this->assertNull($names->schema());
        $this->assertNull($names->schemaOf('invoices'));
    }

    public function testANumericColumnCanGetAName(): void
    {
        // PHP makes the key 2024 an integer.
        $this->assertSame(
            ['id' => 'id', '2024' => 'year'],
            ModelNames::of([])->withGiven('t', ['2024' => 'year'])->properties('t', ['id', '2024'])
        );
    }

    public function testATableWithNoClassStillStops(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'user-profiles' gives no usable class name");

        ModelNames::of([])->classOf('user-profiles');
    }

    public function testAttributesGivesTheGivenAndTheStoredNames(): void
    {
        // In the order of the columns. A stored name of a column that is gone
        // is not in the next base.
        $names = ModelNames::of([])
            ->withStored(
                't',
                [
                    'a'    => ['name' => 'x', 'line' => 6],
                    'gone' => ['name' => 'g', 'line' => 7],
                    'b'    => ['name' => 'y', 'line' => 8],
                ],
                '/b.php'
            )
            ->withGiven('t', ['b' => 'z', 'c' => 'w']);

        $this->assertSame(['c' => 'w', 'b' => 'z', 'a' => 'x'], $names->attributes('t', ['c', 'b', 'a']));
        $this->assertSame([], $names->attributes('other', ['a']));
    }

    /**
     * @dataProvider camelCaseNames
     */
    public function testCamelCaseGivesTheName(string $column, string $expected): void
    {
        $this->assertSame($expected, ModelNames::of(['camelCase' => true])->propertyOf('t', $column));
    }

    public function testCamelCaseGivesTheValue(): void
    {
        // A null value counts as no value (#53).
        $this->assertTrue(ModelNames::of(['camelCase' => true])->camelCase());
        $this->assertFalse(ModelNames::of(['camelCase' => false])->camelCase());
        $this->assertFalse(ModelNames::of(['camelCase' => null])->camelCase());
        $this->assertFalse(ModelNames::of([])->camelCase());
    }

    public function testCamelCaseIsOffByDefault(): void
    {
        $this->assertSame('first_name', ModelNames::of([])->propertyOf('t', 'first_name'));
        $this->assertSame('first_name', ModelNames::of(['camelCase' => false])->propertyOf('t', 'first_name'));
    }

    public function testCheckSchemaPassesOnAServer(): void
    {
        $names = ModelNames::of(['schema' => 'billing']);

        $names->checkSchema($this->adapter('mysql'));
        $this->addToAssertionCount(1);

        $names->checkSchema($this->adapter('pgsql'));
        $this->addToAssertionCount(1);
    }

    public function testCheckSchemaPassesOnSqliteWithNoSchema(): void
    {
        ModelNames::of([])->checkSchema($this->adapter('sqlite'));
        $this->addToAssertionCount(1);
    }

    public function testCheckSchemaStopsOnSqlite(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "crest.php: models.schema is 'billing', but SQLite has no schemas; remove the key"
        );

        ModelNames::of(['schema' => 'billing'])->checkSchema($this->adapter('sqlite'));
    }

    public function testDroppedGivesALineForEachColumnThatIsGone(): void
    {
        $names = ModelNames::of([])->withStored(
            't',
            [
                'zed'  => ['name' => 'z', 'line' => 6],
                'a'    => ['name' => 'x', 'line' => 7],
                'gone' => ['name' => 'g', 'line' => 8],
            ],
            '/b.php'
        );

        $this->assertSame(
            [
                "Dropped the property name g of column 'gone': the table has no such column",
                "Dropped the property name z of column 'zed': the table has no such column",
            ],
            $names->dropped('t', ['a'])
        );
        $this->assertSame([], $names->dropped('other', ['a']));
    }

    public function testFieldOfGivesTheName(): void
    {
        $this->assertSame('customerId', ModelNames::of(['camelCase' => true])->fieldOf('invoices', 'customer_id'));
    }

    public function testFieldOfStopsOnANameThatIsNotUsable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("column 'id' of table 'customers' has no usable property name");

        ModelNames::of([])
            ->withStored('customers', ['id' => ['name' => '2nd', 'line' => 6]], '/b.php')
            ->fieldOf('customers', 'id');
    }

    public function testFieldOfStopsOnTheNameOfAModelProperty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("column 'box' of table 'customers' has no usable property name");

        ModelNames::of([])->withStored('customers', ['box' => ['name' => 'container', 'line' => 6]], '/b.php')
            ->fieldOf('customers', 'box');
    }

    public function testGetSetGivesTheValue(): void
    {
        $this->assertTrue(ModelNames::of(['getSet' => true])->getSet());
        $this->assertFalse(ModelNames::of(['getSet' => false])->getSet());
    }

    public function testGetSetIsOffByDefault(): void
    {
        // A null value counts as no value, as for camelCase.
        $this->assertFalse(ModelNames::of([])->getSet());
        $this->assertFalse(ModelNames::of(['getSet' => null])->getSet());
    }

    public function testGetSetPassesForNamesWithNoConflict(): void
    {
        $this->assertSame(
            ['id' => 'id', 'title' => 'title'],
            ModelNames::of(['getSet' => true])->properties('pages', ['id', 'title'])
        );
    }

    public function testMappedGivesTheTablesOfTheMap(): void
    {
        // PHP makes the key 9 an integer; mapped() gives a string.
        $this->assertSame(
            ['users', '9'],
            ModelNames::of(['classes' => ['users' => 'Member', '9' => 'Nine']])->mapped()
        );
    }

    public function testNoMethodCheckRunsWithNoKey(): void
    {
        $this->assertSame(
            ['source' => 'source', 'first_name' => 'first_name', 'firstName' => 'firstName'],
            ModelNames::of([])->properties('pages', ['source', 'first_name', 'firstName'])
        );
    }

    public function testNoModelsKeyGivesTheDefaults(): void
    {
        $names = ModelNames::of([]);

        $this->assertSame('UserProfiles', $names->classOf('user_profiles'));
        $this->assertSame('first_name', $names->propertyOf('users', 'first_name'));
        $this->assertSame([], $names->mapped());
    }

    public function testNoSchemaKeyIsNoSchema(): void
    {
        $names = ModelNames::of([]);

        $this->assertNull($names->schema());
        $this->assertNull($names->schemaOf('invoices'));
    }

    public function testPropertiesGivesTheNameOfEachColumn(): void
    {
        $this->assertSame(
            ['id' => 'id', 'first_name' => 'firstName'],
            ModelNames::of(['camelCase' => true])->properties('users', ['id', 'first_name'])
        );
    }

    public function testTheOrderOfTheNames(): void
    {
        // --property, then the attribute, then camelCase, then the column.
        $names = ModelNames::of(['camelCase' => true])
            ->withStored(
                't',
                ['a_col' => ['name' => 'storedA', 'line' => 6], 'b_col' => ['name' => 'storedB', 'line' => 7]],
                '/b.php'
            )
            ->withGiven('t', ['a_col' => 'given']);

        $this->assertSame('given', $names->propertyOf('t', 'a_col'));
        $this->assertSame('storedB', $names->propertyOf('t', 'b_col'));
        $this->assertSame('cCol', $names->propertyOf('t', 'c_col'));
    }

    public function testTheSchemaIsTheSchemaOfEachTable(): void
    {
        $names = ModelNames::of(['schema' => 'billing']);

        $this->assertSame('billing', $names->schema());
        $this->assertSame('billing', $names->schemaOf('invoices'));
        $this->assertSame('billing', $names->schemaOf('users'));
    }

    public function testTheWithMethodsKeepGetSet(): void
    {
        $names = ModelNames::of(['getSet' => true]);

        $this->assertTrue($names->withGiven('t', [])->getSet());
        $this->assertTrue($names->withStored('t', [], '/b.php')->getSet());
        $this->assertTrue($names->withFailed('t', 'x')->getSet());
    }

    public function testTheWithMethodsKeepTheSchema(): void
    {
        $names = ModelNames::of(['schema' => 'billing']);

        $this->assertSame('billing', $names->withGiven('t', [])->schema());
        $this->assertSame('billing', $names->withStored('t', [], '/b.php')->schema());
        $this->assertSame('billing', $names->withFailed('t', 'x')->schema());
    }

    public function testTwoNamesThatDifferOnlyInCasePass(): void
    {
        // PHP property names are case-sensitive.
        $this->assertSame(
            ['name' => 'name', 'Name' => 'Name'],
            ModelNames::of([])->properties('t', ['name', 'Name'])
        );
    }

    /**
     * @param list<string> $columns
     *
     * @dataProvider twoPropertiesOneMethod
     */
    public function testTwoPropertiesWithOneMethodNameStop(ModelNames $names, array $columns, string $message): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $names->properties('people', $columns);
    }

    /**
     * An adapter that gives only its type.
     */
    private function adapter(string $type): AdapterInterface
    {
        return $this->createConfiguredStub(AdapterInterface::class, ['getType' => $type]);
    }
}
