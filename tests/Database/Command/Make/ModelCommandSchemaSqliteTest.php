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

use Crest\Command\Make\ModelCommand;
use Crest\Command\Make\ScaffoldCommand;
use Crest\Console\Output;
use Crest\Generator\ModelNames;
use Crest\Paths;
use Crest\Project\Config;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\BillingMigration;
use Crest\Tests\Support\Migrations\MessagesMigration;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Project\SchemaFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Column;
use Phalcon\Di\Di;

use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function str_replace;
use function token_get_all;

use const PHP_EOL;
use const TOKEN_PARSE;

/**
 * make:model with models.schema (#69) on SQLite, with a test adapter that
 * acts as a server with one schema (SchemaSqlite). Infection runs only the
 * unit and the SQLite tests, so these tests hold the reads of the schema
 * there. ModelCommandSchemaTest shows the real behavior on the servers.
 *
 * @group sqlite
 */
final class ModelCommandSchemaSqliteTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    private const MODELS = "['schema' => '" . BillingMigration::SCHEMA . "']";

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model-schema-sqlite');
        $this->front(SchemaFront::class, self::MODELS);
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testAllReadsTheTablesOfTheSchema(): void
    {
        $this->createTables(UsersMigration::class, MessagesMigration::class);

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertStringEndsWith('2 tables: 2 models created, 0 kept, 0 failed.' . PHP_EOL, $this->readStdout());
        $this->assertStringContainsString(
            '\\App\\Models\\Users::class',
            (string) file_get_contents($this->root . '/src/Models/Base/MessagesBase.php')
        );
    }

    public function testAllWithNoTablesNamesTheSchema(): void
    {
        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertSame(
            "No tables to write in the schema '" . BillingMigration::SCHEMA . "'." . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAModuleBaseSetsTheSchema(): void
    {
        // --module needs only the Module.php of the module.
        mkdir($this->root . '/src/Modules/Admin', 0o775, true);
        file_put_contents($this->root . '/src/Modules/Admin/Module.php', '<?php');
        $this->createTables(UsersMigration::class);

        $status = $this->runCommand(['users', '--module=admin']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "        \$this->setSchema('" . BillingMigration::SCHEMA . "');\n",
            (string) file_get_contents($this->root . '/src/Modules/Admin/Models/Base/UsersBase.php')
        );
    }

    public function testAPublishedBaseWithNoSchemaStops(): void
    {
        // A copy that the project published before #69 would write a base
        // with no setSchema(), and the model would read the default schema.
        $this->createTables(UsersMigration::class);

        $published = $this->root . '/resources/stubs/mvc/model-base.stub';

        mkdir($this->root . '/resources/stubs/mvc', 0o775, true);
        file_put_contents(
            $published,
            str_replace('{{ schema }}', '', (string) file_get_contents(Paths::stubs() . '/mvc/model-base.stub'))
        );

        $this->assertStops(
            ['users'],
            'the published stub ' . $published . ' has no {{ schema }}; publish it again with '
            . 'crest stub:publish model-base --force, and make your changes in it again'
        );
    }

    public function testASchemaWithAQuoteIsEscapedInTheBase(): void
    {
        // var_export() escapes the quote, so the base parses. The test
        // adapter has no schema with a quote, so a stub adapter gives the
        // table.
        $adapter = $this->createConfiguredStub(
            AdapterInterface::class,
            [
                'getType'            => 'mysql',
                'tableExists'        => true,
                'describeColumns'    => [new Column('id', ['type' => Column::TYPE_INTEGER])],
                'listTables'         => ['notes'],
                'listViews'          => [],
                'describeReferences' => [],
            ]
        );
        $command = new ModelCommand();
        $config  = Config::discover($this->root);
        $parts   = $command->readTable($adapter, $config, null, ModelNames::of(['schema' => "it's"]), 'notes');

        $command->writeTable($config, $parts, false, new Output($this->stdout, $this->stderr, false));

        $base = (string) file_get_contents($this->root . '/src/Models/Base/NotesBase.php');

        $this->assertStringContainsString("        \$this->setSchema('it\\'s');\n", $base);
        $this->assertNotEmpty(token_get_all($base, TOKEN_PARSE));
    }

    public function testMakeScaffoldReadsTheSchema(): void
    {
        $this->createTables(UsersMigration::class);

        $status = $this->runProjectCommand('make:scaffold', ScaffoldCommand::class, ['users']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "        \$this->setSchema('" . BillingMigration::SCHEMA . "');\n",
            (string) file_get_contents($this->root . '/src/Models/Base/UsersBase.php')
        );
    }

    public function testOneTableIsReadInTheSchema(): void
    {
        // The columns and the keys come from the schema.
        $this->createTables(UsersMigration::class, MessagesMigration::class);

        $status = $this->runCommand(['messages']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            '\\App\\Models\\Users::class',
            (string) file_get_contents($this->root . '/src/Models/Base/MessagesBase.php')
        );
    }

    public function testTheBaseSetsTheSchemaAfterTheSource(): void
    {
        $this->createTables(UsersMigration::class);

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            "    public function initialize(): void\n"
            . "    {\n"
            . "        \$this->setSource('users');\n"
            . "        \$this->setSchema('" . BillingMigration::SCHEMA . "');\n"
            . "    }\n",
            (string) file_get_contents($this->root . '/src/Models/Base/UsersBase.php')
        );
    }

    public function testWithNoSchemaTheTestAdapterFindsNoTable(): void
    {
        // The test adapter finds a table only in its schema.
        $this->front(SchemaFront::class);
        $this->createTables(UsersMigration::class);

        $this->assertStops(['users'], "table 'users' was not found in the database of the 'db' service");
    }
}
