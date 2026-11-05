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

use Crest\Command\Make\ScaffoldCommand;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\ScaffoldAvatarsMigration;
use Crest\Tests\Support\Migrations\ScaffoldCodesMigration;
use Crest\Tests\Support\Migrations\ScaffoldLogsMigration;
use Crest\Tests\Support\Migrations\ScaffoldPairsMigration;
use Crest\Tests\Support\Migrations\ScaffoldProfilesMigration;
use Crest\Tests\Support\Migrations\ScaffoldSequencesMigration;
use Crest\Tests\Support\Migrations\ScaffoldSourcesMigration;
use Crest\Tests\Support\Migrations\ScaffoldUsersMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;

use function file_get_contents;
use function file_put_contents;
use function implode;
use function mkdir;

use const FILE_APPEND;
use const PHP_EOL;

/**
 * make:scaffold reads a table through the `db` service of the project, and
 * writes the files of spec 3.1. The tests run on each driver: DatabaseFront
 * gives the database of the suite. The tests that stop before crest boots the
 * project are in tests/Unit.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ScaffoldCommandTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    private const GUIDANCE = <<<'TEXT'
        The dispatcher finds the controller only when its default namespace is this namespace:

            $dispatcher->setDefaultNamespace('App\Controllers');

        With the default routes, GET /users lists the rows of users.

        These pages have no login and no CSRF check. Add them before you deploy the pages.

        The links and the redirects use the base URI of the url service. A project from crest new
        sets it in UrlProvider. In another project, set it, or the links are not correct:

            $url->setBaseUri('/');

        TEXT;

    private const GUIDANCE_MODULE = <<<'TEXT'
        The admin module finds the controller: its Module class sets the default namespace
        of the dispatcher to App\Modules\Admin\Controllers.

        With the routes from make:module, GET /admin/users lists the rows of users.

        These pages have no login and no CSRF check. Add them before you deploy the pages.

        The links and the redirects use the base URI of the url service. A project from crest new
        sets it in UrlProvider. In another project, set it, or the links are not correct:

            $url->setBaseUri('/');

        TEXT;

    private const NO_RELATIONS = 'No relations for users: the table has no foreign keys, and no foreign keys point '
        . 'to it. Relations come only from foreign keys.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-scaffold');
        $this->front(DatabaseFront::class);
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    /**
     * A column for each half of the accessor check, on v5 and on v6: Model has
     * getMessages() and no setMessages(), and it has setConnectionService()
     * and no getConnectionService().
     *
     * @return iterable<string, array{string}>
     */
    public static function oneAccessor(): iterable
    {
        yield 'only a getter' => ['messages'];
        yield 'only a setter' => ['connection_service'];
    }

    public function testABinaryColumnGivesASkippedLine(): void
    {
        $this->skipBinaryColumnOnOldCphalcon();

        $this->createTables(ScaffoldAvatarsMigration::class);

        $this->assertSame(0, $this->scaffold(['avatars']));

        $base = $this->root . '/src/Forms/Base/AvatarsFormBase.php';

        $this->assertStringContainsString(
            'Created ' . $base . PHP_EOL . 'Skipped picture: a binary column has no field in the form' . PHP_EOL,
            $this->readStdout()
        );
        $this->assertStringNotContainsString('picture', (string) file_get_contents($base));
    }

    /**
     * @dataProvider oneAccessor
     */
    public function testAColumnWithOneAccessorOfTheModelStops(string $column): void
    {
        $this->getConnection()->getPdo()->exec(
            'CREATE TABLE notes (id INTEGER PRIMARY KEY, ' . $column . ' VARCHAR(20))'
        );

        $this->assertScaffoldStops(
            ['notes'],
            "column '" . $column . "' gives the property '" . $column . "', and Phalcon\\Mvc\\Model has a method "
            . 'with that name; give it another name with make:model notes --property=' . $column . '=<name>'
        );
    }

    public function testAColumnWithTheNameOfAModelAccessorStops(): void
    {
        $this->createTables(ScaffoldSourcesMigration::class);

        $this->assertScaffoldStops(
            ['sources'],
            "column 'source' gives the property 'source', and Phalcon\\Mvc\\Model has a method with that name; "
            . 'give it another name with make:model sources --property=source=<name>'
        );
    }

    public function testACompositePrimaryKeyStops(): void
    {
        $this->createTables(ScaffoldPairsMigration::class);

        $this->assertScaffoldStops(
            ['pairs'],
            "table 'pairs' has a composite primary key; make:scaffold needs one column"
        );
    }

    public function testAFirstRunPrintsEachFileAndTheGuidance(): void
    {
        $this->createTables(ScaffoldUsersMigration::class);

        $this->assertSame(0, $this->scaffold(['users']));

        $root = $this->root;

        $this->assertSame(
            implode(
                PHP_EOL,
                [
                    'Created ' . $root . '/src/Models/Base/UsersBase.php',
                    self::NO_RELATIONS,
                    'Created ' . $root . '/src/Models/Users.php',
                    'Created ' . $root . '/src/Forms/Base/UsersFormBase.php',
                    'Created ' . $root . '/src/Forms/UsersForm.php',
                    'Created ' . $root . '/src/Controllers/Base/CrestControllerBase.php',
                    'Created ' . $root . '/src/Controllers/UsersController.php',
                    'Created ' . $root . '/themes/default/users/index.phtml',
                    'Created ' . $root . '/themes/default/users/new.phtml',
                    'Created ' . $root . '/themes/default/users/edit.phtml',
                ]
            ) . PHP_EOL . self::GUIDANCE,
            $this->readStdout()
        );
    }

    public function testAKeyThatIsNotTheFirstColumnIsTheKeyOfThePages(): void
    {
        $this->getConnection()->getPdo()->exec(
            'CREATE TABLE sections (title VARCHAR(50), section_code VARCHAR(10) PRIMARY KEY)'
        );

        $this->assertSame(0, $this->scaffold(['sections']));

        $view = (string) file_get_contents($this->root . '/themes/default/sections/index.phtml');

        $this->assertStringContainsString('<th>Section code</th>', $view);
        $this->assertStringContainsString('$row->section_code', $view);
        $this->assertStringContainsString(
            "'bind'       => ['id' => rawurldecode(\$id)],",
            (string) file_get_contents($this->root . '/src/Controllers/SectionsController.php')
        );
    }

    public function testAMissingTableStops(): void
    {
        $this->assertScaffoldStops(['nothing'], "table 'nothing' was not found in the database of the 'db' service");
    }

    public function testAModuleGetsEachFileItsUrlAndItsGuidance(): void
    {
        $this->createTables(ScaffoldUsersMigration::class);

        $module = $this->root . '/src/Modules/Admin';

        mkdir($module, 0o775, true);
        file_put_contents($module . '/Module.php', "<?php\n");

        $this->assertSame(0, $this->scaffold(['users', '--module=admin']));

        foreach (
            [
                '/Models/Users.php',
                '/Forms/UsersForm.php',
                '/Controllers/UsersController.php',
                '/Controllers/Base/CrestControllerBase.php',
                '/views/users/index.phtml',
                '/views/users/new.phtml',
                '/views/users/edit.phtml',
            ] as $file
        ) {
            $this->assertFileExists($module . $file);
        }

        $controller = (string) file_get_contents($module . '/Controllers/UsersController.php');

        $this->assertStringContainsString('namespace App\\Modules\\Admin\\Controllers;', $controller);
        $this->assertStringContainsString('use App\\Modules\\Admin\\Models\\Users;', $controller);
        $this->assertStringContainsString('use App\\Modules\\Admin\\Forms\\UsersForm;', $controller);
        $this->assertStringContainsString("redirect('admin/users')", $controller);
        $this->assertStringContainsString(
            "\$this->url->get('admin/users/new')",
            (string) file_get_contents($module . '/views/users/index.phtml')
        );
        $this->assertStringContainsString(self::GUIDANCE_MODULE, $this->readStdout());
        $this->assertDirectoryDoesNotExist($this->root . '/src/Controllers');
        $this->assertDirectoryDoesNotExist($this->root . '/themes');
    }

    public function testAnAccessorWithAnotherPropertyNameWorks(): void
    {
        $this->createTables(ScaffoldSourcesMigration::class);

        $this->assertSame(0, $this->runCommand(['sources', '--property=source=origin']));
        $this->assertSame(0, $this->scaffold(['sources']));
        $this->assertStringContainsString(
            "new Text('origin')",
            (string) file_get_contents($this->root . '/src/Forms/Base/SourcesFormBase.php')
        );
    }

    public function testASecondRunAfterANewColumnChangesOnlyTheBases(): void
    {
        $this->createTables(ScaffoldUsersMigration::class);

        $this->assertSame(0, $this->scaffold(['users']));

        $this->getConnection()->getPdo()->exec('ALTER TABLE users ADD COLUMN nickname VARCHAR(20)');
        $this->freshStreams();

        $this->assertSame(0, $this->scaffold(['users']));

        $root = $this->root;
        $kept = ': it exists; pass --force to replace it';

        $this->assertSame(
            implode(
                PHP_EOL,
                [
                    'Updated ' . $root . '/src/Models/Base/UsersBase.php',
                    self::NO_RELATIONS,
                    'Kept ' . $root . '/src/Models/Users.php: it exists; make:model --force replaces it',
                    'Updated ' . $root . '/src/Forms/Base/UsersFormBase.php',
                    'Kept ' . $root . '/src/Forms/UsersForm.php' . $kept,
                    'Kept ' . $root . '/src/Controllers/UsersController.php' . $kept,
                    'Kept ' . $root . '/themes/default/users/index.phtml' . $kept,
                    'Kept ' . $root . '/themes/default/users/new.phtml' . $kept,
                    'Kept ' . $root . '/themes/default/users/edit.phtml' . $kept,
                ]
            ) . PHP_EOL . self::GUIDANCE,
            $this->readStdout()
        );
    }

    public function testATableWithNoPrimaryKeyStops(): void
    {
        $this->createTables(ScaffoldLogsMigration::class);

        $this->assertScaffoldStops(['logs'], "table 'logs' has no primary key; make:scaffold needs one");
    }

    public function testATableWithOnlyAKeyThatIsNotIdentityStops(): void
    {
        $this->createTables(ScaffoldCodesMigration::class);

        $this->assertScaffoldStops(['codes'], "table 'codes' has no columns for a form");
    }

    public function testATableWithOnlyAnIdentityColumnStops(): void
    {
        $this->createTables(ScaffoldSequencesMigration::class);

        $this->assertScaffoldStops(['sequences'], "table 'sequences' has no columns for a form");
    }

    public function testForceReplacesTheControllerTheFormAndTheViewsButNotTheModel(): void
    {
        $this->createTables(ScaffoldUsersMigration::class);

        $this->assertSame(0, $this->scaffold(['users']));

        $model    = $this->root . '/src/Models/Users.php';
        $replaced = [
            $this->root . '/src/Forms/UsersForm.php',
            $this->root . '/src/Controllers/UsersController.php',
            $this->root . '/themes/default/users/index.phtml',
            $this->root . '/themes/default/users/new.phtml',
            $this->root . '/themes/default/users/edit.phtml',
        ];

        foreach ([$model, ...$replaced] as $file) {
            file_put_contents($file, "// mine\n", FILE_APPEND);
        }

        $this->assertSame(0, $this->scaffold(['users', '--force']));

        $this->assertStringContainsString('// mine', (string) file_get_contents($model));

        foreach ($replaced as $file) {
            $this->assertStringNotContainsString('// mine', (string) file_get_contents($file));
        }
    }

    public function testTheFormOfAModuleGoesIntoTheModule(): void
    {
        $this->createTables(ScaffoldUsersMigration::class);

        mkdir($this->root . '/src/Modules/Admin', 0o775, true);
        file_put_contents($this->root . '/src/Modules/Admin/Module.php', "<?php\n");

        $this->assertSame(0, $this->scaffold(['users', '--module=admin']));

        $form = (string) file_get_contents($this->root . '/src/Modules/Admin/Forms/UsersForm.php');
        $base = (string) file_get_contents($this->root . '/src/Modules/Admin/Forms/Base/UsersFormBase.php');

        $this->assertStringContainsString('namespace App\\Modules\\Admin\\Forms;', $form);
        $this->assertStringContainsString('namespace App\\Modules\\Admin\\Forms\\Base;', $base);
        $this->assertStringContainsString('run make:scaffold users --module=admin.', $base);
        $this->assertDirectoryDoesNotExist($this->root . '/src/Forms');
    }

    public function testTheModelAndTheFormAreWritten(): void
    {
        $this->createTables(ScaffoldUsersMigration::class);

        $this->assertSame(0, $this->scaffold(['users']));

        $this->assertFileExists($this->root . '/src/Models/Base/UsersBase.php');
        $this->assertFileExists($this->root . '/src/Models/Users.php');

        $base = (string) file_get_contents($this->root . '/src/Forms/Base/UsersFormBase.php');

        $this->assertStringContainsString('namespace App\\Forms\\Base;', $base);
        $this->assertStringContainsString('run make:scaffold users.', $base);
        $this->assertStringContainsString("new Text('name')", $base);
        $this->assertStringContainsString(
            'namespace App\\Forms;',
            (string) file_get_contents($this->root . '/src/Forms/UsersForm.php')
        );
    }

    public function testTheNamesFollowTheModelsKey(): void
    {
        $this->createTables(ScaffoldUsersMigration::class);
        $this->front(DatabaseFront::class, "['classes' => ['users' => 'User']]");

        $this->assertSame(0, $this->scaffold(['users']));

        $this->assertFileExists($this->root . '/src/Forms/UserForm.php');
        $this->assertFileExists($this->root . '/src/Forms/Base/UserFormBase.php');
    }

    public function testTheNamesOfTheTableGoIntoTheRoute(): void
    {
        $this->createTables(ScaffoldProfilesMigration::class);

        $this->assertSame(0, $this->scaffold(['user_profiles']));

        $this->assertFileExists($this->root . '/themes/default/user-profiles/index.phtml');

        $controller = (string) file_get_contents($this->root . '/src/Controllers/UserProfilesController.php');

        $this->assertStringContainsString("redirect('user-profiles')", $controller);
        $this->assertStringContainsString("\$this->view->pick('user-profiles/index')", $controller);

        $this->createTables(ScaffoldUsersMigration::class);
        $this->front(DatabaseFront::class, "['classes' => ['users' => 'User']]");

        $this->assertSame(0, $this->scaffold(['users']));

        $this->assertFileExists($this->root . '/themes/default/user/index.phtml');
        $this->assertStringContainsString(
            "redirect('user')",
            (string) file_get_contents($this->root . '/src/Controllers/UserController.php')
        );
    }

    /**
     * The command stops with the error on stderr, and makes no folder.
     *
     * @param list<string> $arguments
     */
    private function assertScaffoldStops(array $arguments, string $error): void
    {
        $this->assertSame(1, $this->scaffold($arguments));
        $this->assertSame('crest: ' . $error . PHP_EOL, $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/src/Forms');
        $this->assertDirectoryDoesNotExist($this->root . '/src/Models');
        $this->assertDirectoryDoesNotExist($this->root . '/src/Controllers');
    }

    /**
     * @param list<string> $arguments
     */
    private function scaffold(array $arguments): int
    {
        return $this->runProjectCommand('make:scaffold', ScaffoldCommand::class, $arguments);
    }
}
