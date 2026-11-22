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

use Crest\Command\Make\RepositoryCommand;
use Crest\Generator\ArtifactWriter;
use Crest\Tests\Support\GeneratesInAScratchProject;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\Project\EmptyFront;
use Crest\Tests\Support\Project\FailingConnectionFront;
use Crest\Tests\Support\Project\FailingFront;
use Crest\Tests\Support\Project\MemoryConnectionFront;
use Crest\Tests\Support\Project\NotAConnectionFront;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function mkdir;

use const PHP_EOL;

/**
 * make:repository (#84) in an ADR project, with a DataMapper connection to a
 * SQLite database in memory (MemoryConnectionFront). The database suites run
 * the repository that it writes on each driver.
 */
final class RepositoryCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    private const BASE = '/src/Repository/Base/UsersRepositoryBase.php';

    private const REPOSITORY = '/src/Repository/UsersRepository.php';

    protected function setUp(): void
    {
        $this->startScratchProject('make-repository');
        $this->front(MemoryConnectionFront::class);
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testABaseWithoutTheMarkerIsKept(): void
    {
        mkdir($this->root . '/src/Repository/Base', 0o775, true);
        file_put_contents($this->root . self::BASE, "<?php\n// mine\n");

        $this->assertSame(0, $this->runCommand(['users']));
        $this->assertStringStartsWith(
            'Kept ' . $this->root . self::BASE . ': it has no "' . ArtifactWriter::MARKER . '" line',
            $this->readStdout()
        );
        $this->assertSame("<?php\n// mine\n", (string) file_get_contents($this->root . self::BASE));
    }

    public function testADbServiceThatFailsStops(): void
    {
        $this->front(FailingConnectionFront::class);

        $this->assertStops(['users'], "the 'db' service failed: could not connect");
    }

    public function testADbServiceThatIsNotAConnectionStops(): void
    {
        $this->front(NotAConnectionFront::class);

        $this->assertStops(['users'], "the 'db' service is stdClass, not a Phalcon\DataMapper\Pdo\Connection");
    }

    public function testAMissingTableStops(): void
    {
        $this->assertStops(['nothing'], "table 'nothing' was not found in the database of the 'db' service");
    }

    public function testAnExistingRepositoryIsKept(): void
    {
        $this->runCommand(['users']);
        file_put_contents($this->root . self::REPOSITORY, "<?php\n// mine\n");
        $this->freshStreams();

        $this->assertSame(0, $this->runCommand(['users']));
        $this->assertSame(
            'Kept ' . $this->root . self::REPOSITORY . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertSame("<?php\n// mine\n", (string) file_get_contents($this->root . self::REPOSITORY));
    }

    public function testAnMvcContainerStops(): void
    {
        $this->front(DatabaseFront::class);

        $this->assertStops(
            ['users'],
            'Phalcon\Di\FactoryDefault is not a Phalcon container; make:repository needs the container of an ADR '
            . 'project'
        );
    }

    public function testAnMvcProjectGetsTheFlavorError(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->assertStops(
            ['users'],
            "make:repository does not support the 'mvc' flavor of this project; it supports: adr"
        );
    }

    public function testARepositoryPathOutsidePsr4StopsBeforeTheBoot(): void
    {
        $this->writeCrestPhp(
            "['flavor' => 'adr', 'bootstrap' => \\" . FailingFront::class . "::class, 'paths' => ['repository' => "
            . "'lib/Repository']]"
        );

        $this->assertStops(['users'], "no psr-4 autoload entry covers 'lib/Repository'");
    }

    public function testATableThatGivesNoClassStopsBeforeTheBoot(): void
    {
        $this->front(FailingFront::class);

        $this->assertStops(
            ['9 lives'],
            "'9 lives' gives no usable class name; expected a table name like 'users' or 'user_profiles'"
        );
    }

    public function testATableWithACompositePrimaryKeyStops(): void
    {
        $this->assertStops(['pairs'], "table 'pairs' has a composite primary key; make:repository needs one column");
    }

    public function testATableWithNoPrimaryKeyStops(): void
    {
        $this->assertStops(['notes'], "table 'notes' has no primary key; make:repository needs one");
    }

    public function testForceReplacesTheRepository(): void
    {
        $this->runCommand(['users']);
        file_put_contents($this->root . self::REPOSITORY, "<?php\n// mine\n");

        $this->assertSame(0, $this->runCommand(['users', '--force']));
        $this->assertStringContainsString(
            'final class UsersRepository extends UsersRepositoryBase',
            (string) file_get_contents($this->root . self::REPOSITORY)
        );
    }

    public function testNoDbServiceStops(): void
    {
        $this->front(EmptyFront::class);

        $this->assertStops(
            ['users'],
            "the project container has no 'db' service; register a Phalcon\DataMapper\Pdo\Connection with the "
            . 'alias db (crest make:provider Db)'
        );
    }

    public function testTheFirstRunWritesTheBaseTheRepositoryAndTheGuidance(): void
    {
        // Asserted whole: the guidance is what the developer reads next.
        $this->assertSame(0, $this->runCommand(['users']));
        $this->assertSame(
            'Created ' . $this->root . self::BASE . PHP_EOL
            . 'Created ' . $this->root . self::REPOSITORY . PHP_EOL
            . 'Nothing registers it: the container makes it when a class asks for it. Give' . PHP_EOL
            . '\App\Repository\UsersRepository to the constructor of an action or a domain class.' . PHP_EOL
            . 'It needs the Phalcon\DataMapper\Pdo\Connection service, which the DbProvider' . PHP_EOL
            . 'of crest new --database registers.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheHelp(): void
    {
        $definition = (new RepositoryCommand())->define();
        $arguments  = $definition->getArguments();
        $force      = $definition->findOption('force');

        $this->assertSame('make:repository', $definition->getName());
        $this->assertSame('Create an ADR repository for a database table', $definition->getDescription());
        $this->assertCount(1, $arguments);
        $this->assertSame('table', $arguments[0]->name);
        $this->assertTrue($arguments[0]->required);
        $this->assertSame('Table name, e.g. users', $arguments[0]->description);
        $this->assertNotNull($force);
        $this->assertSame('Overwrite an existing repository', $force->description);
    }

    public function testTheSecondRunLeavesTheBaseAsItIs(): void
    {
        $this->runCommand(['users']);
        $this->freshStreams();

        $this->runCommand(['users']);

        $this->assertStringNotContainsString(self::BASE, $this->readStdout());
    }

    public function testTheWholeBaseIsRendered(): void
    {
        // Asserted whole: this is generated code that nobody reviews.
        $this->runCommand(['users']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\\Repository\\Base;\n"
            . "\n"
            . "use Phalcon\\DataMapper\\Pdo\\Connection;\n"
            . "use Phalcon\\DataMapper\\Query\\Insert;\n"
            . "use Phalcon\\DataMapper\\Query\\QueryFactory;\n"
            . "use Phalcon\\DataMapper\\Query\\Update;\n"
            . "\n"
            . "/**\n"
            . " * Generated by crest. Do not edit this file: crest writes it again when you\n"
            . " * run make:repository users. Put your code into the repository that\n"
            . " * extends it.\n"
            . " */\n"
            . "abstract class UsersRepositoryBase\n"
            . "{\n"
            . "    /**\n"
            . "     * The columns of the table, in their order.\n"
            . "     */\n"
            . "    public const COLUMNS = [\n"
            . "        'id',\n"
            . "        'name',\n"
            . "        'email',\n"
            . "    ];\n"
            . "\n"
            . "    public const PRIMARY_KEY = 'id';\n"
            . "\n"
            . "    public const TABLE = 'users';\n"
            . "\n"
            . "    public function __construct(\n"
            . "        protected readonly Connection \$connection,\n"
            . "        protected readonly QueryFactory \$queryFactory,\n"
            . "    ) {\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * Inserts a row, and gives the id of the new row.\n"
            . "     *\n"
            . "     * @param array<string, mixed> \$row Column => value\n"
            . "     */\n"
            . "    public function add(array \$row): string\n"
            . "    {\n"
            . "        \$insert = \$this->queryFactory->newInsert(\$this->connection);\n"
            . "\n"
            . "        \$insert->into(static::TABLE);\n"
            . "        \$this->values(\$insert, \$row);\n"
            . "        \$insert->perform();\n"
            . "\n"
            . "        return \$insert->getLastInsertId();\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * Deletes the row with the id.\n"
            . "     */\n"
            . "    public function delete(int|string \$id): void\n"
            . "    {\n"
            . "        \$this->queryFactory->newDelete(\$this->connection)\n"
            . "            ->from(static::TABLE)\n"
            . "            ->where(static::PRIMARY_KEY . ' = ', \$id)\n"
            . "            ->perform();\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * The row with the id, or null when there is none.\n"
            . "     *\n"
            . "     * @return array<string, mixed>|null\n"
            . "     */\n"
            . "    public function find(int|string \$id): ?array\n"
            . "    {\n"
            . "        \$select = \$this->queryFactory->newSelect(\$this->connection);\n"
            . "\n"
            . "        \$select->from(static::TABLE)\n"
            . "            ->columns(static::COLUMNS)\n"
            . "            ->where(static::PRIMARY_KEY . ' = ', \$id)\n"
            . "            ->limit(1);\n"
            . "\n"
            . "        \$row = \$this->connection->fetchOne(\$select->getStatement(), \$select->getBindValues());\n"
            . "\n"
            . "        return [] === \$row ? null : \$row;\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * Changes the columns of \$row in the row with the id.\n"
            . "     *\n"
            . "     * @param array<string, mixed> \$row Column => value\n"
            . "     */\n"
            . "    public function update(int|string \$id, array \$row): void\n"
            . "    {\n"
            . "        \$update = \$this->queryFactory->newUpdate(\$this->connection);\n"
            . "\n"
            . "        \$update->from(static::TABLE);\n"
            . "        \$this->values(\$update, \$row);\n"
            . "        \$update->where(static::PRIMARY_KEY . ' = ', \$id)->perform();\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * Gives the values of \$row to the query. set() writes NULL: column()\n"
            . "     * binds no value for null.\n"
            . "     *\n"
            . "     * @param array<string, mixed> \$row Column => value\n"
            . "     */\n"
            . "    private function values(Insert|Update \$query, array \$row): void\n"
            . "    {\n"
            . "        foreach (\$row as \$column => \$value) {\n"
            . "            if (null === \$value) {\n"
            . "                \$query->set(\$column);\n"
            . "            } else {\n"
            . "                \$query->column(\$column, \$value);\n"
            . "            }\n"
            . "        }\n"
            . "    }\n"
            . "}\n",
            (string) file_get_contents($this->root . self::BASE)
        );
    }

    public function testTheWholeRepositoryIsRendered(): void
    {
        $this->runCommand(['users']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\\Repository;\n"
            . "\n"
            . "use App\\Repository\\Base\\UsersRepositoryBase;\n"
            . "\n"
            . "/**\n"
            . " * The repository of the table users. Add the queries of your\n"
            . " * application here.\n"
            . " */\n"
            . "final class UsersRepository extends UsersRepositoryBase\n"
            . "{\n"
            . "}\n",
            (string) file_get_contents($this->root . self::REPOSITORY)
        );
    }

    /**
     * The command stops with the error on stderr, and writes no file.
     *
     * @param list<string> $arguments
     */
    private function assertStops(array $arguments, string $error): void
    {
        $this->assertSame(1, $this->runCommand($arguments));
        $this->assertSame('crest: ' . $error . PHP_EOL, $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/src/Repository');
    }

    /**
     * Empty streams for the output of the next run.
     */
    private function freshStreams(): void
    {
        $this->closeStreams();
        $this->captureStreams();
    }

    /**
     * An ADR crest.php that names the front controller.
     *
     * @param class-string $class
     */
    private function front(string $class): void
    {
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . $class . '::class]');
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('make:repository', RepositoryCommand::class, $arguments);
    }
}
