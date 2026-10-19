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

namespace Crest\Tests\Database\Command\Make;

use Crest\Paths;
use Crest\Project\Config;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\InvoicesMigration;
use Crest\Tests\Support\Migrations\InvoicesWithBuyerMigration;
use Crest\Tests\Support\Migrations\NotesWithQuoteMigration;
use Crest\Tests\Support\Migrations\OddMigration;
use Crest\Tests\Support\Migrations\PeopleMigration;
use Crest\Tests\Support\Migrations\PetsMigration;
use Crest\Tests\Support\Migrations\TotalsMigration;
use Crest\Tests\Support\Migrations\UserProfilesDashMigration;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\ResultsetInterface;

use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function str_replace;
use function token_get_all;

use const PHP_EOL;
use const TOKEN_PARSE;

/**
 * The names of make:model (#53): models.classes, --property, a PropertyName
 * attribute and models.camelCase. The tests run on each driver: DatabaseFront
 * gives the database of the suite. A test starts with no tables, and writes
 * its crest.php.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelCommandNamesTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    /**
     * The end of the line for a table with no keys, after "No relations for <table>".
     */
    private const NO_KEYS = ': the table has no foreign keys, and no foreign keys point to it. '
        . 'Relations come only from foreign keys.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model-names');
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testABaseWithAColumnMapThatCrestCannotReadGivesANote(): void
    {
        // A cast before the array: crest reads only a columnMap() that
        // returns an array of plain strings.
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);
        $this->runCommand(['users', '--property=name=fullName']);

        $baseFile = $this->model('Base/UsersBase.php');
        file_put_contents(
            $baseFile,
            str_replace('return [', 'return (array) [', (string) file_get_contents($baseFile))
        );
        $this->freshStreams();

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Updated ' . $baseFile . PHP_EOL
            . 'Note: crest cannot read columnMap() of ' . $baseFile . ', so it cannot list the renamed properties'
            . PHP_EOL
            . 'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Kept ' . $this->model('Users.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAChangedAttributeRenamesTheProperty(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);
        $this->runCommand(['users', '--property=name=fullName']);

        $baseFile = $this->model('Base/UsersBase.php');
        file_put_contents(
            $baseFile,
            str_replace("name: 'fullName'", "name: 'displayName'", (string) file_get_contents($baseFile))
        );
        $this->freshStreams();

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Updated ' . $baseFile . PHP_EOL
            . 'Renamed $fullName to $displayName: change the code that uses it' . PHP_EOL
            . 'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Kept ' . $this->model('Users.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringContainsString(
            "    public ?string \$displayName = null;\n",
            (string) file_get_contents($baseFile)
        );
    }

    public function testAColumnThatIsNotAPropertyNameGetsAName(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(OddMigration::class);

        $this->assertSame(0, $this->runCommand(['odd', '--property=first name=firstName']));

        $base = (string) file_get_contents($this->model('Base/OddBase.php'));

        $this->assertStringContainsString(
            "#[\\Crest\\Attribute\\PropertyName('first name', name: 'firstName')]\n",
            $base
        );
        $this->assertStringContainsString("    public ?string \$firstName = null;\n", $base);
        $this->assertStringContainsString("            'first name' => 'firstName',\n", $base);
    }

    public function testADroppedColumnDropsItsPropertyName(): void
    {
        // The key column of invoices gets a new name, so the attribute of
        // the old column is dropped, with the alias of the old key.
        $this->front(DatabaseFront::class);
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $this->runCommand(['invoices', '--property=customer_id=clientId']);
        $this->migration(InvoicesMigration::class)->drop();
        $this->createTables(InvoicesWithBuyerMigration::class);
        $this->freshStreams();

        $status = $this->runCommand(['invoices']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Updated ' . $this->model('Base/InvoicesBase.php') . PHP_EOL
            . "Dropped the property name clientId of column 'customer_id': the table has no such column" . PHP_EOL
            . "Dropped the alias Customer of 'belongsTo invoices(customer_id) -> customers(id)': "
            . 'the database has no such key' . PHP_EOL
            . 'Kept ' . $this->model('Invoices.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringNotContainsString(
            'PropertyName',
            (string) file_get_contents($this->model('Base/InvoicesBase.php'))
        );
    }

    public function testAFlipOfCamelCaseRenamesTheProperties(): void
    {
        // customer_id is in the key to customers, so the base of customers
        // must be written again.
        $this->front(DatabaseFront::class);
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $this->runCommand(['invoices']);
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $this->freshStreams();

        $status = $this->runCommand(['invoices']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Updated ' . $this->model('Base/InvoicesBase.php') . PHP_EOL
            . 'Renamed $customer_id to $customerId: change the code that uses it' . PHP_EOL
            . 'Run make:model for customers too: its relations use the property names of invoices' . PHP_EOL
            . 'Kept ' . $this->model('Invoices.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAllDoesNotNameATableThatItWrites(): void
    {
        // The relations of customers use customer_id, and this run writes
        // customers too.
        $this->front(DatabaseFront::class);
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $this->runCommand(['--all']);
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $this->freshStreams();

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Table customers' . PHP_EOL
            . 'Updated ' . $this->model('Base/CustomersBase.php') . PHP_EOL
            . 'Kept ' . $this->model('Customers.php') . ': it exists; pass --force to replace it' . PHP_EOL
            . PHP_EOL
            . 'Table invoices' . PHP_EOL
            . 'Updated ' . $this->model('Base/InvoicesBase.php') . PHP_EOL
            . 'Renamed $customer_id to $customerId: change the code that uses it' . PHP_EOL
            . 'Kept ' . $this->model('Invoices.php') . ': it exists; pass --force to replace it' . PHP_EOL
            . PHP_EOL
            . '2 tables: 0 models created, 2 kept, 0 failed.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAllNamesAnExcludedTableToRunAgain(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $this->runCommand(['--all']);
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $this->freshStreams();

        $status = $this->runCommand(['--all', '--exclude=customers']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Table invoices' . PHP_EOL
            . 'Updated ' . $this->model('Base/InvoicesBase.php') . PHP_EOL
            . 'Renamed $customer_id to $customerId: change the code that uses it' . PHP_EOL
            . 'Run make:model for customers too: its relations use the property names of invoices' . PHP_EOL
            . 'Kept ' . $this->model('Invoices.php') . ': it exists; pass --force to replace it' . PHP_EOL
            . PHP_EOL
            . '1 table: 0 models created, 1 kept, 0 failed.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAllUsesTheMap(): void
    {
        // The line for Users has the wrong case, so it gives a note.
        $this->front(DatabaseFront::class, "['classes' => ['user-profiles' => 'UserProfile', 'Users' => 'Member']]");
        $this->createTables(UserProfilesDashMigration::class, UsersMigration::class);

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Note: models.classes names the table Users, which the database does not have; did you mean users?'
            . PHP_EOL
            . 'Table user-profiles' . PHP_EOL
            . 'Created ' . $this->model('Base/UserProfileBase.php') . PHP_EOL
            . 'No relations for user-profiles' . self::NO_KEYS . PHP_EOL
            . 'Created ' . $this->model('UserProfile.php') . PHP_EOL
            . PHP_EOL
            . 'Table users' . PHP_EOL
            . 'Created ' . $this->model('Base/UsersBase.php') . PHP_EOL
            . 'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Created ' . $this->model('Users.php') . PHP_EOL
            . PHP_EOL
            . '2 tables: 2 models created, 0 kept, 0 failed.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAllWithCamelCaseUsesThePropertyNamesOfBothTables(): void
    {
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(0, $this->runCommand(['--all']));
        $this->assertStringContainsString(
            "        \$this->hasMany(\n"
            . "            'id',\n"
            . "            \\App\\Models\\Invoices::class,\n"
            . "            'customerId',\n"
            . "            ['alias' => 'Invoices']\n"
            . "        );\n",
            (string) file_get_contents($this->model('Base/CustomersBase.php'))
        );
        $this->assertStringContainsString(
            "            'customerId',\n",
            (string) file_get_contents($this->model('Base/InvoicesBase.php'))
        );
    }

    public function testAMapLineGivesAClassToANameWithNoClass(): void
    {
        $this->front(DatabaseFront::class, "['classes' => ['user-profiles' => 'UserProfile']]");
        $this->createTables(UserProfilesDashMigration::class);

        $this->assertSame(0, $this->runCommand(['user-profiles']));
        $this->assertStringContainsString(
            "        \$this->setSource('user-profiles');\n",
            (string) file_get_contents($this->model('Base/UserProfileBase.php'))
        );
        $this->assertFileExists($this->model('UserProfile.php'));
    }

    public function testAMapLineGivesTheClass(): void
    {
        $this->front(DatabaseFront::class, "['classes' => ['users' => 'User']]");
        $this->createTables(UsersMigration::class);

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Created ' . $this->model('Base/UserBase.php') . PHP_EOL
            . 'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Created ' . $this->model('User.php') . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringContainsString(
            "abstract class UserBase extends Model\n",
            (string) file_get_contents($this->model('Base/UserBase.php'))
        );
        $this->assertStringContainsString(
            "final class User extends UserBase\n",
            (string) file_get_contents($this->model('User.php'))
        );
    }

    public function testANewMapLineStartsANewBase(): void
    {
        // crest reads only the base of the new class, so the attribute of the
        // old base does not move. The old files stay (spec 3.5).
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);
        $this->runCommand(['users', '--property=name=fullName']);
        $this->front(DatabaseFront::class, "['classes' => ['users' => 'User']]");

        $this->assertSame(0, $this->runCommand(['users']));
        $this->assertStringContainsString(
            "    public ?string \$name = null;\n",
            (string) file_get_contents($this->model('Base/UserBase.php'))
        );
        $this->assertFileExists($this->model('Base/UsersBase.php'));
        $this->assertFileExists($this->model('Users.php'));
    }

    public function testANumericColumnKeepsItsNameOnTheNextRun(): void
    {
        // PHP makes the column 2024 an integer key. The next run reads the
        // attribute, and columnMap() quotes the column.
        $this->front(DatabaseFront::class);
        $this->createTables(TotalsMigration::class);
        $this->runCommand(['totals', '--property=2024=year']);
        $this->freshStreams();

        $status = $this->runCommand(['totals']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'No relations for totals' . self::NO_KEYS . PHP_EOL
            . 'Kept ' . $this->model('Totals.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringContainsString(
            "            'id'   => 'id',\n            '2024' => 'year',\n",
            (string) file_get_contents($this->model('Base/TotalsBase.php'))
        );
    }

    public function testAPropertyForAColumnThatTheTableDoesNotHaveStops(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);

        $this->assertStops(
            ['users', '--property=Name=fullName'],
            "--property: table 'users' has no column 'Name'; did you mean name?"
        );
    }

    public function testAPropertyNameGoesIntoTheBase(): void
    {
        // Asserted whole: this is generated code that nobody reviews.
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);

        $status = $this->runCommand(['users', '--property=name=fullName']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Created ' . $this->model('Base/UsersBase.php') . PHP_EOL
            . 'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Created ' . $this->model('Users.php') . PHP_EOL,
            $this->readStdout()
        );
        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\\Models\\Base;\n"
            . "\n"
            . "use Phalcon\\Mvc\\Model;\n"
            . "\n"
            . "/**\n"
            . " * Generated by crest. Do not edit this file: crest writes it again when you\n"
            . " * run make:model users. Put your code into the model that extends it.\n"
            . " *\n"
            . " * You can change a property name in a PropertyName attribute: crest keeps\n"
            . " * it when it writes this file again. Then change the code that uses the\n"
            . " * property.\n"
            . " */\n"
            . "#[\\Crest\\Attribute\\PropertyName('name', name: 'fullName')]\n"
            . "abstract class UsersBase extends Model\n"
            . "{\n"
            . "    public ?int \$id = null;\n"
            . "    public ?string \$fullName = null;\n"
            . "    public ?float \$score = null;\n"
            . "    public ?string \$price = null;\n"
            . "    public ?string \$created = null;\n"
            . "\n"
            . "    public function initialize(): void\n"
            . "    {\n"
            . "        \$this->setSource('users');\n"
            . "    }\n"
            . "\n"
            . "    public function columnMap(): array\n"
            . "    {\n"
            . "        return [\n"
            . "            'id'      => 'id',\n"
            . "            'name'    => 'fullName',\n"
            . "            'score'   => 'score',\n"
            . "            'price'   => 'price',\n"
            . "            'created' => 'created',\n"
            . "        ];\n"
            . "    }\n"
            . "}\n",
            (string) file_get_contents($this->model('Base/UsersBase.php'))
        );
    }

    public function testAPublishedPropertyFragmentWithNoPropertyWorksWithNoNames(): void
    {
        // A copy that a project published before #53 has {{ column }}, not
        // {{ property }}.
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);
        mkdir($this->root . '/resources/stubs/mvc', 0o775, true);
        file_put_contents(
            $this->root . '/resources/stubs/mvc/fragment-model-property.stub',
            str_replace(
                '{{ property }}',
                '{{ column }}',
                (string) file_get_contents(Paths::stubs() . '/mvc/fragment-model-property.stub')
            )
        );

        $this->assertSame(0, $this->runCommand(['users']));
        $this->assertStringContainsString(
            "    public ?string \$name = null;\n",
            (string) file_get_contents($this->model('Base/UsersBase.php'))
        );
    }

    public function testAPublishedStubWithNoColumnMapStops(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);
        $published = $this->publishOldBaseStub();

        $this->assertStops(
            ['users', '--property=name=fullName'],
            'the published stub ' . $published . ' has no {{ columnMap }}; publish it again with '
            . 'crest stub:publish model-base --force, and make your changes in it again'
        );
    }

    public function testAPublishedStubWithNoColumnMapWorksWithNoNames(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);
        $this->publishOldBaseStub();

        $this->assertSame(0, $this->runCommand(['users']));
    }

    public function testAQuoteInAColumnIsEscaped(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class, NotesWithQuoteMigration::class);

        $this->assertSame(0, $this->runCommand(['notes', "--property=it's_id=authorId"]));

        $base = (string) file_get_contents($this->model('Base/NotesBase.php'));

        $this->assertStringContainsString(
            "#[\\Crest\\Attribute\\PropertyName('it\\'s_id', name: 'authorId')]\n",
            $base
        );
        $this->assertStringContainsString("            'id'       => 'id',\n", $base);
        $this->assertStringContainsString("            'it\\'s_id' => 'authorId',\n", $base);
        $this->assertStringContainsString("            'authorId',\n", $base);
        $this->assertNotEmpty(token_get_all($base, TOKEN_PARSE));
    }

    public function testASecondRunKeepsThePropertyName(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);
        $this->runCommand(['users', '--property=name=fullName']);
        $this->freshStreams();

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Kept ' . $this->model('Users.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringContainsString(
            "    public ?string \$fullName = null;\n",
            (string) file_get_contents($this->model('Base/UsersBase.php'))
        );
    }

    public function testCamelCaseGivesEachPropertyAndAColumnMap(): void
    {
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(0, $this->runCommand(['invoices']));

        $base = (string) file_get_contents($this->model('Base/InvoicesBase.php'));

        $this->assertStringContainsString("    public ?int \$customerId = null;\n", $base);
        $this->assertStringContainsString("            'customer_id' => 'customerId',\n", $base);
        $this->assertStringContainsString(
            "        \$this->belongsTo(\n"
            . "            'customerId',\n"
            . "            \\App\\Models\\Customers::class,\n"
            . "            'id',\n"
            . "            ['alias' => 'Customer']\n"
            . "        );\n",
            $base
        );
        $this->assertStringNotContainsString('PropertyName', $base);
    }

    public function testEachColumnOfPropertyGetsItsName(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(UsersMigration::class);

        $this->assertSame(0, $this->runCommand(['users', '--property=name=fullName,score=points']));

        $base = (string) file_get_contents($this->model('Base/UsersBase.php'));

        $this->assertStringContainsString("    public ?string \$fullName = null;\n", $base);
        $this->assertStringContainsString("    public ?float \$points = null;\n", $base);
    }

    public function testTheModelsReadRowsThroughTheNames(): void
    {
        // The "done when" of #53. The classes stay loaded in the PHP process,
        // so no other test may use the tables people and pets.
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $people = $this->migration(PeopleMigration::class);
        $pets   = $this->migration(PetsMigration::class);
        $this->createTables($people, $pets);
        $people->insert('ann');
        $pets->insert('rex', 1);

        $this->assertSame(0, $this->runCommand(['people', '--property=first_name=givenName']));
        $this->assertSame(0, $this->runCommand(['pets']));

        require $this->model('Base/PeopleBase.php');
        require $this->model('People.php');
        require $this->model('Base/PetsBase.php');
        require $this->model('Pets.php');

        // The models get their services from the default DI. An earlier test
        // can have made the default DI, so the test sets the DI of the front.
        Di::setDefault((new DatabaseFront())->boot());

        /** @var class-string<Model> $class */
        $class = Config::discover($this->root)->namespaceFor('model') . '\\Pets';
        $pet   = $class::findFirst();

        $this->assertInstanceOf(Model::class, $pet);
        $this->assertSame(['id' => 1, 'petName' => 'rex', 'personId' => 1], $pet->toArray());

        $person = $pet->getRelated('Person');

        $this->assertInstanceOf(Model::class, $person);
        $this->assertSame(['id' => 1, 'givenName' => 'ann'], $person->toArray());

        $owned = $person->getRelated('Pets');

        $this->assertInstanceOf(ResultsetInterface::class, $owned);
        $this->assertSame([['id' => 1, 'petName' => 'rex', 'personId' => 1]], $owned->toArray());
    }

    public function testThePropertyNamesComeBeforeTheRelationAliases(): void
    {
        $this->front(DatabaseFront::class);
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(0, $this->runCommand(['invoices', '--property=customer_id=clientId']));
        $this->assertStringContainsString(
            " *\n"
            . " * You can change a property name in a PropertyName attribute: crest keeps\n"
            . " * it when it writes this file again. Then change the code that uses the\n"
            . " * property.\n"
            . " *\n"
            . " * You can change the alias in a RelationAlias attribute: crest keeps it when\n"
            . " * it writes this file again.\n"
            . " */\n"
            . "#[\\Crest\\Attribute\\PropertyName('customer_id', name: 'clientId')]\n"
            . "#[\\Crest\\Attribute\\RelationAlias('belongsTo invoices(customer_id) -> customers(id)', "
            . "alias: 'Customer')]\n"
            . "abstract class InvoicesBase extends Model\n",
            (string) file_get_contents($this->model('Base/InvoicesBase.php'))
        );
    }

    public function testTheRelationsUsePropertyNamesOnBothSides(): void
    {
        // The attribute of the base of customers gives code for id; camelCase
        // gives customerId. Each base uses the names of the other table.
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(0, $this->runCommand(['customers', '--property=id=code']));
        $this->assertSame(0, $this->runCommand(['invoices']));
        $this->assertStringContainsString(
            "            'code',\n            \\App\\Models\\Invoices::class,\n            'customerId',\n",
            (string) file_get_contents($this->model('Base/CustomersBase.php'))
        );
        $this->assertStringContainsString(
            "            'customerId',\n            \\App\\Models\\Customers::class,\n            'code',\n",
            (string) file_get_contents($this->model('Base/InvoicesBase.php'))
        );
    }

    public function testTheRelationsUseTheClassOfTheMap(): void
    {
        $this->front(DatabaseFront::class, "['classes' => ['customers' => 'Customer']]");
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);

        $this->assertSame(0, $this->runCommand(['invoices']));
        $this->assertStringContainsString(
            "            \\App\\Models\\Customer::class,\n",
            (string) file_get_contents($this->model('Base/InvoicesBase.php'))
        );
    }

    /**
     * A file in the `model` path of the scratch project.
     */
    private function model(string $file): string
    {
        return $this->root . '/src/Models/' . $file;
    }

    /**
     * The packaged model-base.stub with no {{ columnMap }}, published into
     * the scratch project, as a project that published it before #53.
     */
    private function publishOldBaseStub(): string
    {
        $published = $this->root . '/resources/stubs/mvc/model-base.stub';

        mkdir($this->root . '/resources/stubs/mvc', 0o775, true);
        file_put_contents(
            $published,
            str_replace('{{ columnMap }}', '', (string) file_get_contents(Paths::stubs() . '/mvc/model-base.stub'))
        );

        return $published;
    }
}
