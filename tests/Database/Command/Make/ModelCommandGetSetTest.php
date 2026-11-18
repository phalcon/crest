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
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\DriversMigration;
use Crest\Tests\Support\Migrations\ScaffoldSourcesMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_replace;

use const PHP_EOL;

/**
 * The base of make:model with models.getSet (#70, spec 3.2 to 3.5): protected
 * properties, a getter and a setter for each, the published stubs, and the
 * lines of a change. These tests read the text of the base only; they do not
 * load the classes (ModelGetSetRowsTest does).
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelCommandGetSetTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    private const GET_SET = "['getSet' => true]";

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model-get-set');
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testAMethodOfTheModelFailsOnlyItsTableWithAll(): void
    {
        // The column source gives getSource(), a method of Phalcon\Mvc\Model.
        $this->front(DatabaseFront::class, self::GET_SET);
        $this->createTables(ScaffoldSourcesMigration::class, DriversMigration::class);
        $this->freshStreams();

        $this->assertSame(1, $this->runCommand(['--all']));
        $this->assertStringContainsString(
            "Failed: column 'source' of table 'sources' gives the method getSource(), which Phalcon\\Mvc\\Model has; "
            . 'give it another name with --property="source=<name>"',
            $this->readStdout()
        );
        $this->assertFileExists($this->model('Base/DriversBase.php'));
    }

    public function testAnOldPublishedCopyWorksWithNoKey(): void
    {
        // Copies that a project published before #70 have no {{ visibility }}
        // and no {{ methods }}. With no key, crest needs neither.
        $this->front(DatabaseFront::class);
        $this->createTables(DriversMigration::class);
        $this->publishOldStub('model-base', 'methods');
        $this->publishOldStub('fragment-model-property', 'visibility', 'public');

        $this->assertSame(0, $this->runCommand(['drivers']));
        $this->assertStringContainsString(
            "    public ?string \$first_name = null;\n",
            (string) file_get_contents($this->model('Base/DriversBase.php'))
        );
    }

    public function testAPropertyNameGivesTheMethodNames(): void
    {
        $this->front(DatabaseFront::class, self::GET_SET);
        $this->createTables(DriversMigration::class);

        $this->assertSame(0, $this->runCommand(['drivers', '--property=first_name=givenName']));

        $base = (string) file_get_contents($this->model('Base/DriversBase.php'));

        $this->assertStringContainsString("    protected ?string \$givenName = null;\n", $base);
        $this->assertStringContainsString($this->accessors('GivenName', 'givenName', 'string'), $base);
    }

    public function testAPublishedBaseWithNoMethodsStops(): void
    {
        $this->front(DatabaseFront::class, self::GET_SET);
        $this->createTables(DriversMigration::class);
        $published = $this->publishOldStub('model-base', 'methods');

        $this->assertStops(
            ['drivers'],
            'the published stub ' . $published . ' has no {{ methods }}; publish it again with '
            . 'crest stub:publish model-base --force, and make your changes in it again'
        );
    }

    public function testAPublishedPropertyFragmentWithNoVisibilityStops(): void
    {
        $this->front(DatabaseFront::class, self::GET_SET);
        $this->createTables(DriversMigration::class);
        $published = $this->publishOldStub('fragment-model-property', 'visibility', 'public');

        $this->assertStops(
            ['drivers'],
            'the published stub ' . $published . ' has no {{ visibility }}; publish it again with '
            . 'crest stub:publish fragment-model-property --force, and make your changes in it again'
        );
    }

    public function testCamelCaseKeepsTheMethodNames(): void
    {
        // A change of camelCase changes the properties, not the methods.
        $this->front(DatabaseFront::class, "['camelCase' => true, 'getSet' => true]");
        $this->createTables(DriversMigration::class);

        $this->assertSame(0, $this->runCommand(['drivers']));

        $base = (string) file_get_contents($this->model('Base/DriversBase.php'));

        $this->assertStringContainsString("    protected ?string \$firstName = null;\n", $base);
        $this->assertStringContainsString($this->accessors('FirstName', 'firstName', 'string'), $base);
    }

    public function testTheBaseHasProtectedPropertiesAndTheMethods(): void
    {
        $this->front(DatabaseFront::class, self::GET_SET);
        $this->createTables(DriversMigration::class);

        $this->assertSame(0, $this->runCommand(['drivers']));
        $this->assertStringEndsWith(
            "abstract class DriversBase extends Model\n{\n"
            . "    protected ?int \$id = null;\n"
            . "    protected ?string \$first_name = null;\n"
            . "    protected ?int \$age = null;\n\n"
            . "    public function initialize(): void\n    {\n        \$this->setSource('drivers');\n    }\n"
            . $this->accessors('Id', 'id', 'int')
            . $this->accessors('FirstName', 'first_name', 'string')
            . $this->accessors('Age', 'age', 'int')
            . "}\n",
            (string) file_get_contents($this->model('Base/DriversBase.php'))
        );
    }

    public function testTheLinesOfAChangeOfTheMethods(): void
    {
        $base = $this->model('Base/DriversBase.php');

        $this->front(DatabaseFront::class, self::GET_SET);
        $this->createTables(DriversMigration::class);
        $this->freshStreams();
        $this->runCommand(['drivers']);
        $this->assertStringNotContainsString('Note: the properties', $this->readStdout());

        // A new property name gives new method names.
        $this->freshStreams();
        $this->runCommand(['drivers', '--property=first_name=givenName']);
        $this->assertStringContainsString(
            'Renamed $first_name to $givenName: change the code that uses it' . PHP_EOL
            . 'Renamed getFirstName() and setFirstName() to getGivenName() and setGivenName(): '
            . 'change the code that uses them' . PHP_EOL,
            $this->readStdout()
        );

        // The key from true to false: the base has no methods.
        $this->front(DatabaseFront::class);
        $this->freshStreams();
        $this->runCommand(['drivers']);
        $this->assertStringContainsString(
            'Removed getId() and setId(): change the code that uses them' . PHP_EOL
            . 'Removed getGivenName() and setGivenName(): change the code that uses them' . PHP_EOL
            . 'Removed getAge() and setAge(): change the code that uses them' . PHP_EOL,
            $this->readStdout()
        );

        // The key from false to true: the public properties are protected now.
        $this->front(DatabaseFront::class, self::GET_SET);
        $this->freshStreams();
        $this->runCommand(['drivers']);
        $this->assertStringContainsString(
            sprintf(
                'Note: the properties of %s are protected now; code outside the model goes through the getters '
                . 'and setters' . PHP_EOL,
                $base
            ),
            $this->readStdout()
        );
    }

    /**
     * The getter and the setter of one property, as spec 3.2 gives them.
     */
    private function accessors(string $method, string $property, string $type): string
    {
        return "\n    public function get{$method}(): ?{$type}\n    {\n        return \$this->{$property};\n    }\n"
            . "\n    public function set{$method}(?{$type} \$value): static\n    {\n"
            . "        \$this->{$property} = \$value;\n\n        return \$this;\n    }\n";
    }

    /**
     * A file in the `model` path of the scratch project.
     */
    private function model(string $file): string
    {
        return $this->root . '/src/Models/' . $file;
    }

    /**
     * The packaged stub with one placeholder replaced, published into the
     * scratch project, as a project that published it before #70.
     */
    private function publishOldStub(string $stub, string $placeholder, string $value = ''): string
    {
        $published = $this->root . '/resources/stubs/mvc/' . $stub . '.stub';

        if (false === is_dir($this->root . '/resources/stubs/mvc')) {
            mkdir($this->root . '/resources/stubs/mvc', 0o775, true);
        }

        file_put_contents(
            $published,
            str_replace(
                '{{ ' . $placeholder . ' }}',
                $value,
                (string) file_get_contents(Paths::stubs() . '/mvc/' . $stub . '.stub')
            )
        );

        return $published;
    }
}
