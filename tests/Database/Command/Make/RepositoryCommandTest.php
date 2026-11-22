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

use Crest\Command\Make\RepositoryCommand;
use Crest\Project\Config;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\GeneratesInAScratchProject;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Project\DataMapperFront;
use Crest\Tests\Support\TestDatabase;
use Phalcon\DataMapper\Query\QueryFactory;

use function class_exists;
use function method_exists;
use function sprintf;

/**
 * make:repository reads a table on each driver, and the repository that it
 * writes reads and writes the rows of the table (#84).
 *
 * The generated classes are declared in this process, so they use a
 * namespace that no other test uses.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class RepositoryCommandTest extends AbstractDatabaseTestCase
{
    use GeneratesInAScratchProject;

    private const NAMESPACE = 'Scaffolded\\Adr';

    protected function setUp(): void
    {
        parent::setUp();

        $this->startScratchProject('make-repository-db');
        $this->writeComposerJson([self::NAMESPACE . '\\' => 'src/']);
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . DataMapperFront::class . '::class]');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();

        parent::tearDown();
    }

    public function testTheRepositoryReadsAndWritesTheRows(): void
    {
        $this->createTables(UsersMigration::class);

        $this->assertSame(0, $this->runProjectCommand('make:repository', RepositoryCommand::class, ['users']));

        require_once $this->root . '/src/Repository/Base/UsersRepositoryBase.php';
        require_once $this->root . '/src/Repository/UsersRepository.php';

        // The class from crest.php, as make:repository finds it. A literal
        // class name here would make PHPStan look for a class that exists only
        // at run time.
        $class = Config::discover($this->root)->namespaceFor('repository') . '\\UsersRepository';

        if (false === class_exists($class)) {
            $this->fail(sprintf('make:repository wrote no class %s', $class));
        }

        $repository = new $class(TestDatabase::connection(), new QueryFactory());

        if (
            false === method_exists($repository, 'add')
            || false === method_exists($repository, 'find')
            || false === method_exists($repository, 'update')
            || false === method_exists($repository, 'delete')
        ) {
            $this->fail('the repository has not the methods of its base');
        }

        $id = $repository->add(['name' => 'Ann', 'score' => 1.5]);

        $this->assertColumn('Ann', 'name', $repository->find($id));

        $repository->update($id, ['name' => 'Bea']);

        $this->assertColumn('Bea', 'name', $repository->find($id));

        $repository->delete($id);

        $this->assertNull($repository->find($id));

        // DataMapper binds no value for null in column(): the base uses
        // set(), which writes NULL. A server stops on a token with no value.
        $id = $repository->add(['name' => 'Cid', 'score' => null]);

        $this->assertColumn(null, 'score', $repository->find($id));

        $repository->update($id, ['score' => 2.5]);
        $repository->update($id, ['score' => null]);

        $this->assertColumn(null, 'score', $repository->find($id));
    }

    /**
     * The row has the column with the value.
     */
    private function assertColumn(?string $expected, string $column, mixed $row): void
    {
        $this->assertIsArray($row);
        $this->assertArrayHasKey($column, $row);
        $this->assertSame($expected, $row[$column]);
    }
}
