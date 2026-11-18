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

use Crest\Project\Config;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\CouriersMigration;
use Crest\Tests\Support\Migrations\DriversMigration;
use Crest\Tests\Support\Migrations\ParcelsMigration;
use Crest\Tests\Support\Migrations\TrucksMigration;
use Crest\Tests\Support\Migrations\VansMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;
use Phalcon\Mvc\Model;

/**
 * Phalcon reads and saves a row through the getters and the setters of a base
 * with models.getSet (#70, "done when" of the spec). The generated classes stay
 * loaded in the PHP process, so each test uses its own tables, and no other
 * test loads the classes of drivers, trucks, couriers, parcels or vans.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelGetSetRowsTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model-get-set-rows');
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testARowIsReadAndSavedThroughTheMethods(): void
    {
        $this->front(DatabaseFront::class, "['getSet' => true]");
        $drivers = $this->migration(DriversMigration::class);
        $trucks  = $this->migration(TrucksMigration::class);
        $this->createTables($drivers, $trucks);
        $drivers->insert('Ann', 30);
        $trucks->insert(1, 'AB-1');

        $this->assertSame(0, $this->runCommand(['--all']));

        $namespace = $this->load(['Drivers', 'Trucks']);
        $driver    = $this->first($namespace . '\\Drivers');

        // The getters give the typed values.
        $this->assertSame(1, $this->call($driver, 'getId'));
        $this->assertSame('Ann', $this->call($driver, 'getFirstName'));
        $this->assertSame(30, $this->call($driver, 'getAge'));

        // The setters return the model, so a chain works.
        $chained = $this->call($driver, 'setFirstName', 'Bea');

        $this->assertSame($driver, $chained);
        $this->assertInstanceOf(Model::class, $chained);
        $this->call($chained, 'setAge', 31);
        $this->assertTrue($driver->save());

        $again = $this->first($namespace . '\\Drivers');

        $this->assertSame('Bea', $this->call($again, 'getFirstName'));
        $this->assertSame(31, $this->call($again, 'getAge'));

        // From outside, the protected property goes through the methods.
        $this->assertSame('Bea', $again->first_name);
        $again->first_name = 'Cid';
        $this->assertSame('Cid', $this->call($again, 'getFirstName'));
        $this->assertSame(['id' => 1, 'first_name' => 'Cid', 'age' => 31], $again->toArray());

        $truck   = $this->first($namespace . '\\Trucks');
        $related = $truck->getRelated('Driver');
        $called  = $this->call($truck, 'getDriver');

        $this->assertInstanceOf(Model::class, $related);
        $this->assertInstanceOf(Model::class, $called);
        $this->assertSame('Bea', $this->call($related, 'getFirstName'));
        $this->assertSame('Bea', $this->call($called, 'getFirstName'));
    }

    public function testAssignWithNoSettersWritesTheProtectedProperty(): void
    {
        // With disableAssignSetters, assign() writes the property from the
        // Model class (spec 8, item 5).
        $this->front(DatabaseFront::class, "['getSet' => true]");
        $vans = $this->migration(VansMigration::class);
        $this->createTables($vans);
        $vans->insert(30);

        $this->assertSame(0, $this->runCommand(['vans']));

        // The setter of the model throws: the test fails if assign() calls it.
        $model = $this->root . '/src/Models/Vans.php';
        $code  = (string) file_get_contents($model);

        file_put_contents(
            $model,
            substr($code, 0, (int) strrpos($code, '}'))
            . "\n    public function setAge(?int \$value): static\n"
            . "    {\n"
            . "        throw new \\LogicException('setAge() ran');\n"
            . "    }\n"
            . "}\n"
        );

        $van = $this->first($this->load(['Vans']) . '\\Vans');

        Model::setup(['disableAssignSetters' => true]);

        try {
            $van->assign(['age' => 40]);
        } finally {
            Model::setup(['disableAssignSetters' => false]);
        }

        $this->assertSame(40, $this->call($van, 'getAge'));
    }

    public function testPhalconFindsTheMethodsOfACamelCaseName(): void
    {
        $this->front(DatabaseFront::class, "['camelCase' => true, 'getSet' => true]");
        $couriers = $this->migration(CouriersMigration::class);
        $this->createTables($couriers);
        $couriers->insert('Ann', 30);

        $this->assertSame(0, $this->runCommand(['couriers']));

        $class   = $this->load(['Couriers']) . '\\Couriers';
        $courier = $this->first($class);

        $this->assertSame('Ann', $courier->firstName);
        $courier->firstName = 'Bea';
        $this->assertSame('Bea', $this->call($courier, 'getFirstName'));
        $this->assertTrue($courier->save());
        $this->assertSame('Bea', $this->call($this->first($class), 'getFirstName'));
    }

    public function testPhalconFindsTheMethodsOfAPropertyNameAndOddNames(): void
    {
        // _note gives getNote(), and line_1 gives getLine1(): Phalcon must
        // find them from the property name.
        $this->front(DatabaseFront::class, "['getSet' => true]");
        $parcels = $this->migration(ParcelsMigration::class);
        $this->createTables($parcels);
        $parcels->insert('Ann', 'fragile', 'first');

        $this->assertSame(0, $this->runCommand(['parcels', '--property=first_name=givenName']));

        $class  = $this->load(['Parcels']) . '\\Parcels';
        $parcel = $this->first($class);

        $this->assertSame('Ann', $parcel->givenName);
        $this->assertSame('fragile', $parcel->_note);
        $this->assertSame('first', $parcel->line_1);

        $parcel->givenName = 'Bea';
        $parcel->_note     = 'heavy';
        $parcel->line_1    = 'second';

        $this->assertSame('Bea', $this->call($parcel, 'getGivenName'));
        $this->assertSame('heavy', $this->call($parcel, 'getNote'));
        $this->assertSame('second', $this->call($parcel, 'getLine1'));
        $this->assertTrue($parcel->save());

        $again = $this->first($class);

        $this->assertSame('Bea', $this->call($again, 'getGivenName'));
        $this->assertSame('heavy', $this->call($again, 'getNote'));
        $this->assertSame('second', $this->call($again, 'getLine1'));
    }

    /**
     * Calls a method of a generated class. The methods are not in
     * Phalcon\Mvc\Model, so the static analysis does not know them.
     */
    private function call(Model $row, string $method, mixed ...$arguments): mixed
    {
        return $row->{$method}(...$arguments);
    }

    /**
     * The first row of a generated class.
     */
    private function first(string $class): Model
    {
        /** @var class-string<Model> $class */
        $row = $class::findFirst();

        $this->assertInstanceOf(Model::class, $row);

        return $row;
    }

    /**
     * Loads the base and the model of each class, sets the DI of the front as
     * the default DI, and gives the namespace of the models. An earlier test
     * can have made the default DI.
     *
     * @param list<string> $classes
     */
    private function load(array $classes): string
    {
        foreach ($classes as $class) {
            require $this->root . '/src/Models/Base/' . $class . 'Base.php';
            require $this->root . '/src/Models/' . $class . '.php';
        }

        Di::setDefault((new DatabaseFront())->boot());

        return Config::discover($this->root)->namespaceFor('model');
    }
}
