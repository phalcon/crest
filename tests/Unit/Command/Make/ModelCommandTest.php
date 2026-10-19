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

namespace Crest\Tests\Unit\Command\Make;

use Crest\Command\Make\ModelCommand;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\Project\FailingDatabaseFront;
use Crest\Tests\Support\Project\FailingFront;
use Crest\Tests\Support\Project\NoDatabaseFront;
use Crest\Tests\Support\Project\WrongContainerFront;
use Crest\Tests\Support\Project\WrongDatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;
use PHPUnit\Framework\TestCase;

/**
 * make:model stops before it reads a table (spec 3). The checks of the
 * command line come first (#52, spec 3.1). These tests need no database:
 * each stop comes before crest boots DatabaseFront. The tests that read a
 * table are in tests/Database.
 */
final class ModelCommandTest extends TestCase
{
    use RunsMakeModel;

    protected function setUp(): void
    {
        $this->startScratchProject('make-model');
        $this->front(DatabaseFront::class);
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
    }

    public function testABadModelsKeyStopsBeforeTheBoot(): void
    {
        // FailingFront fails to boot. The models check runs first, so its
        // error is the one on stderr.
        $this->front(FailingFront::class, "'yes'");

        $this->assertStops(['users'], 'crest.php: models must be an array');
    }

    public function testAColumnTwoTimesInPropertyStops(): void
    {
        $this->assertStops(['users', '--property=a=b, a=c'], "--property: the column 'a' is in the list two times");
    }

    public function testAContainerThatIsNotADiStops(): void
    {
        $this->front(WrongContainerFront::class);

        $this->assertStops(
            ['users'],
            'stdClass is not a Phalcon DI container; make:model needs the DI of an MVC project'
        );
    }

    public function testADbServiceThatFailsStops(): void
    {
        $this->front(FailingDatabaseFront::class);

        $this->assertStops(
            ['users'],
            "the 'db' service failed: SQLSTATE[HY000] [14] unable to open database file"
        );
    }

    public function testADbServiceThatIsNotAnAdapterStops(): void
    {
        $this->front(WrongDatabaseFront::class);

        $this->assertStops(['users'], "the 'db' service is stdClass, not a Phalcon database adapter");
    }

    public function testAllAndATableStop(): void
    {
        $this->assertStops(['users', '--all'], 'give a table name or --all, not both');
    }

    public function testAModelPathWithNoNamespaceStopsBeforeTheBoot(): void
    {
        // FailingFront fails to boot. The namespace check runs first, so its
        // error is the one on stderr.
        $this->writeCrestPhp(
            "['flavor' => 'mvc', 'bootstrap' => \\" . FailingFront::class . '::class, '
            . "'paths' => ['model' => 'models']]"
        );

        $this->assertStops(['users'], "no psr-4 autoload entry covers 'models'");
    }

    public function testAnAdrProjectStops(): void
    {
        $this->writeCrestPhp();

        $this->assertStops(
            ['users'],
            "make:model does not support the 'adr' flavor of this project; it supports: mvc"
        );
    }

    public function testANameThatGivesNoClassStops(): void
    {
        // FailingFront fails to boot. The name check runs first, so its
        // error is the one on stderr.
        $this->front(FailingFront::class);

        $this->assertStops(
            ['user-profiles'],
            "'user-profiles' gives no usable class name; expected a table name like 'users' or 'user_profiles'"
        );
    }

    public function testAnEmptyExcludeNameStops(): void
    {
        $this->assertStops(['--all', '--exclude=users,,orders'], '--exclude has an empty table name');
    }

    public function testAnEmptyExcludeStops(): void
    {
        $this->assertStops(['--all', '--exclude='], '--exclude has an empty table name');
    }

    public function testAPropertyWithAnEmptyNameStops(): void
    {
        $this->assertStops(
            ['users', '--property=first name='],
            "--property: 'first name' has no name; use column=name"
        );
    }

    public function testAPropertyWithNoColumnStops(): void
    {
        $this->assertStops(['users', '--property==firstName'], '--property has an empty column name');
    }

    public function testAPropertyWithNoNameStops(): void
    {
        $this->assertStops(
            ['users', '--property=first name'],
            "--property: 'first name' has no name; use column=name"
        );
    }

    public function testAPropertyWithOnlySpacesAsTheNameStops(): void
    {
        $this->assertStops(
            ['users', '--property=first name= '],
            "--property: 'first name' has no name; use column=name"
        );
    }

    public function testAReservedNameStops(): void
    {
        $this->front(FailingFront::class);

        $this->assertStops(['list'], "'list' gives the class List, but PHP reserves that name");
    }

    public function testDefinitionNamesItself(): void
    {
        $definition = (new ModelCommand())->define();

        $this->assertSame('make:model', $definition->getName());
        $this->assertSame(
            'Create an MVC model from a database table, or from each table with --all '
            . '(relations come only from foreign keys)',
            $definition->getDescription()
        );
    }

    public function testExcludeWithoutAllStops(): void
    {
        $this->assertStops(['users', '--exclude=orders'], '--exclude is only for --all');
    }

    public function testNoDbServiceStops(): void
    {
        $this->front(NoDatabaseFront::class);

        $this->assertStops(
            ['users'],
            "the project container has no 'db' service; register one with a provider (crest make:provider Db)"
        );
    }

    public function testNoTableAndNoAllStop(): void
    {
        $this->assertStops([], 'give a table name, or --all');
    }

    public function testPropertyWithAllStops(): void
    {
        $this->assertStops(['--all', '--property=a=b'], '--property is only for one table, not --all');
    }

    public function testThePropertyOptionHasItsHelp(): void
    {
        $option = (new ModelCommand())->define()->findOption('property');

        $this->assertNotNull($option);
        $this->assertSame(
            'For one table: property names, as column=name, separated by commas',
            $option->description
        );
    }
}
