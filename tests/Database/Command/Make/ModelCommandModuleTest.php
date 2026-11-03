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

use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\InvoicesMigration;
use Crest\Tests\Support\Migrations\MessagesMigration;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;

use function file_get_contents;
use function file_put_contents;
use function mkdir;

use const PHP_EOL;

/**
 * make:model --module (#55): the models go into the Models folder of the
 * module, in its namespace, and the relations name models in the same
 * module. The tests run on each driver: DatabaseFront gives the database of
 * the suite. A test starts with no tables.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelCommandModuleTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model-module');
        $this->front(DatabaseFront::class);

        // --module needs only the Module.php of the module.
        mkdir($this->root . '/src/Modules/Admin', 0o775, true);
        file_put_contents($this->root . '/src/Modules/Admin/Module.php', '<?php');
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testAllWritesEachTableIntoTheModule(): void
    {
        $this->createTables(UsersMigration::class, MessagesMigration::class);

        $status = $this->runCommand(['--all', '--module=admin']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->model('Users.php'));
        $this->assertFileExists($this->model('Base/UsersBase.php'));
        $this->assertFileExists($this->model('Messages.php'));
        $this->assertFileExists($this->model('Base/MessagesBase.php'));
        $this->assertStringEndsWith('2 tables: 2 models created, 0 kept, 0 failed.' . PHP_EOL, $this->readStdout());
        $this->assertDirectoryDoesNotExist($this->root . '/src/Models');
    }

    public function testARelationNamesTheClassInTheModule(): void
    {
        $this->createTables(UsersMigration::class, MessagesMigration::class);

        $status = $this->runCommand(['messages', '--module=admin']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            '\\App\\Modules\\Admin\\Models\\Users::class',
            (string) file_get_contents($this->model('Base/MessagesBase.php'))
        );
    }

    public function testOneTableGoesIntoTheModule(): void
    {
        $this->createTables(UsersMigration::class);

        $status = $this->runCommand(['users', '--module=admin']);

        $this->assertSame(0, $status);

        $model = (string) file_get_contents($this->model('Users.php'));

        $this->assertStringContainsString("namespace App\\Modules\\Admin\\Models;\n", $model);
        $this->assertStringContainsString("use App\\Modules\\Admin\\Models\\Base\\UsersBase;\n", $model);
        $this->assertStringContainsString(
            "namespace App\\Modules\\Admin\\Models\\Base;\n",
            (string) file_get_contents($this->model('Base/UsersBase.php'))
        );
        $this->assertSame(
            'Created ' . $this->model('Base/UsersBase.php') . PHP_EOL
            . 'No relations for users: the table has no foreign keys, and no foreign keys point to it. '
            . 'Relations come only from foreign keys.' . PHP_EOL
            . 'Created ' . $this->model('Users.php') . PHP_EOL,
            $this->readStdout()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/src/Models');
    }

    public function testTheNoteNamesTheModule(): void
    {
        // The Note line of #71 names the option too: the related table must
        // be written into the same module.
        $this->createTables(UsersMigration::class, MessagesMigration::class);
        $this->runCommand(['--all', '--module=admin']);
        $this->front(DatabaseFront::class, "['classes' => ['users' => 'User']]");
        $this->freshStreams();

        $status = $this->runCommand(['users', '--module=admin']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'Note: the old files of users stay: ' . $this->model('Users.php') . ' and '
            . $this->model('Base/UsersBase.php') . '; move your code to User, delete them, '
            . 'then run make:model --module=admin for messages' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheRunLineNamesTheModule(): void
    {
        // A developer who runs the line as it is must write into the module.
        $this->createTables(CustomersMigration::class, InvoicesMigration::class);
        $this->runCommand(['invoices', '--module=admin']);
        $this->front(DatabaseFront::class, "['camelCase' => true]");
        $this->freshStreams();

        $status = $this->runCommand(['invoices', '--module=admin']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'Run make:model --module=admin for customers too: its relations use the property names of invoices'
            . PHP_EOL,
            $this->readStdout()
        );
    }

    private function model(string $file): string
    {
        return $this->root . '/src/Modules/Admin/Models/' . $file;
    }
}
