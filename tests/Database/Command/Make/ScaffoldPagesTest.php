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

use Crest\Command\Make\ModuleCommand;
use Crest\Command\Make\ScaffoldCommand;
use Crest\Project\Config;
use Crest\Tests\Database\Command\AbstractNewCommandTestCase;
use Crest\Tests\Support\Migrations\BillingMigration;
use Crest\Tests\Support\Migrations\BillingVendorsMigration;
use Crest\Tests\Support\Migrations\ScaffoldCountriesMigration;
use Crest\Tests\Support\Migrations\ScaffoldProfilesMigration;
use Crest\Tests\Support\Migrations\ScaffoldSlotsMigration;
use Crest\Tests\Support\Migrations\ScaffoldUsersMigration;
use PDO;
use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Router;
use Phalcon\Talon\Database\Schema\AbstractSchema;

use function file_get_contents;
use function file_put_contents;
use function is_numeric;
use function method_exists;
use function parse_str;
use function parse_url;
use function sprintf;
use function str_replace;
use function substr_count;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * The pages of make:scaffold, through the generated application. There is no
 * HTTP: a request is $_GET, $_POST and $_SERVER, and the application answers in
 * this process, as GeneratedMvcProjectTest does. The project connects with its
 * own DbProvider, to the database of the suite or to its SQLite file.
 *
 * PHP_SELF is the path of the request, as a server gives it behind a rewrite,
 * because each link and each redirect must not depend on it (spec 3.12).
 *
 * The generated classes stay loaded in the PHP process, so each test has a
 * namespace of its own. Each request has a new DI: the default DI is the DI of
 * the last boot.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ScaffoldPagesTest extends AbstractNewCommandTestCase
{
    /** @var array<mixed> */
    private array $get = [];

    /** @var array<mixed> */
    private array $post = [];

    /** @var array<mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_GET    = $this->get;
        $_POST   = $this->post;
        $_SERVER = $this->server;

        parent::tearDown();
    }

    public function testABadIdIsNotFound(): void
    {
        $project = $this->scaffolded('BadId', 'users', ScaffoldUsersMigration::class);

        $this->assertStringContainsString('Users was not found', $this->page($project, '/users/edit/abc'));
    }

    public function testABadNumberInTheSearchGivesNoError(): void
    {
        $project = $this->scaffolded('BadNumber', 'users', ScaffoldUsersMigration::class);

        $this->assertStringContainsString('method="get"', $this->page($project, '/users/search?age=abc'));
    }

    public function testACreateWithAKeyThatExistsKeepsTheRow(): void
    {
        $project = $this->scaffolded('KeyExists', 'countries', ScaffoldCountriesMigration::class);

        $this->assertRedirect(
            $this->request($project, 'POST', '/countries/create', ['code' => 'fr', 'name' => 'x']),
            '/countries'
        );

        $content = $this->content(
            $this->request($project, 'POST', '/countries/create', ['code' => 'fr', 'name' => 'y'])
        );

        $this->assertStringContainsString('already exists', $content);
        $this->assertStringContainsString('value="y"', $content);
        $this->assertStringContainsString('action="/countries/create"', $content);
        $this->assertSame('x', $this->value($project, "SELECT name FROM countries WHERE code = 'fr'"));
    }

    public function testADeleteRemovesTheRow(): void
    {
        $project = $this->scaffolded('Delete', 'users', ScaffoldUsersMigration::class);
        $id      = $this->insertUser($project, 'ann');

        $this->assertRedirect($this->request($project, 'POST', '/users/delete/' . $id), '/users');
        $this->assertSame(0, $this->rows($project, 'users'));
    }

    public function testADeleteThatTheModelRefusesShowsItsMessage(): void
    {
        $project = $this->scaffolded('Refuses', 'users', ScaffoldUsersMigration::class);
        $model   = $project . '/src/Models/Users.php';
        $id      = $this->insertUser($project, 'ann');

        file_put_contents(
            $model,
            str_replace(
                "    // If you add initialize(), call parent::initialize() first.\n",
                "    public function beforeDelete(): bool\n    {\n"
                . "        \$this->appendMessage(new \\Phalcon\\Messages\\Message('this row stays'));\n\n"
                . "        return false;\n    }\n",
                (string) file_get_contents($model)
            )
        );

        $content = $this->content($this->request($project, 'POST', '/users/delete/' . $id));

        $this->assertStringContainsString('this row stays', $content);
        $this->assertStringContainsString('method="get"', $content);
        $this->assertSame(1, $this->rows($project, 'users'));
    }

    public function testAFalseValueKeepsItsOption(): void
    {
        // Only PostgreSQL gives BOOLEAN (spec 2).
        if ('pgsql' !== $this->getDriver()) {
            $this->markTestSkipped('only PostgreSQL gives a BOOLEAN column');
        }

        $project = $this->scaffolded('FalseValue', 'users', ScaffoldUsersMigration::class);

        $this->assertRedirect(
            $this->request($project, 'POST', '/users/create', ['name' => 'ann', 'active' => '0']),
            '/users'
        );

        $id = $this->number($project, "SELECT id FROM users WHERE name = 'ann'");

        $this->assertFalse($this->value($project, 'SELECT active FROM users WHERE id = ' . $id));
        $this->assertMatchesRegularExpression(
            '/<option(?=[^>]*value="0")(?=[^>]*selected)[^>]*>/',
            $this->page($project, '/users/edit/' . $id)
        );

        $this->assertRedirect(
            $this->request($project, 'POST', '/users/save/' . $id, ['name' => 'ann', 'active' => '0']),
            '/users'
        );

        $this->assertFalse($this->value($project, 'SELECT active FROM users WHERE id = ' . $id));
        $this->assertStringContainsString('<td>ann</td>', $this->page($project, '/users/search?active=0'));
    }

    public function testAGetChangesNothing(): void
    {
        $project = $this->scaffolded('Get', 'users', ScaffoldUsersMigration::class);
        $id      = $this->insertUser($project, 'ann');

        foreach (['/users/delete/' . $id, '/users/save/' . $id, '/users/create'] as $uri) {
            $this->assertRedirect($this->request($project, 'GET', $uri), '/users');
        }

        $this->assertSame(1, $this->rows($project, 'users'));
        $this->assertSame('ann', $this->value($project, 'SELECT name FROM users'));
    }

    public function testAKeyWithAReservedNameWorks(): void
    {
        $project = $this->scaffolded('Reserved', 'slots', ScaffoldSlotsMigration::class);

        $this->pdo($project)->exec("INSERT INTO slots (name) VALUES ('x')");

        $this->assertStringContainsString('/slots/edit/1', $this->page($project, '/slots'));
        $this->assertRedirect($this->request($project, 'POST', '/slots/save/1', ['name' => 'y']), '/slots');
        $this->assertSame('y', $this->value($project, 'SELECT name FROM slots'));
    }

    public function testAModuleServesThePages(): void
    {
        $project = $this->project('Module', ScaffoldUsersMigration::class);

        $this->assertSame(
            0,
            $this->runThroughKernel('make:module', ModuleCommand::class, ['admin', '--directory', $project]),
            $this->readStderr()
        );
        $this->assertSame(
            0,
            $this->runThroughKernel(
                'make:scaffold',
                ScaffoldCommand::class,
                ['users', '--module=admin', '--directory', $project]
            ),
            $this->readStderr()
        );

        $this->assertStringContainsString('/admin/users/new', $this->page($project, '/admin/users', true));
        $this->assertRedirect(
            $this->request($project, 'POST', '/admin/users/create', ['name' => 'ann'], true),
            '/admin/users'
        );

        $id = $this->number($project, "SELECT id FROM users WHERE name = 'ann'");

        $this->assertStringContainsString(
            'action="/admin/users/save/' . $id . '"',
            $this->page($project, '/admin/users/edit/' . $id, true)
        );
        $this->assertRedirect(
            $this->request($project, 'POST', '/admin/users/save/' . $id, ['name' => 'bob'], true),
            '/admin/users'
        );
        $this->assertSame('bob', $this->value($project, 'SELECT name FROM users'));
        $this->assertRedirect(
            $this->request($project, 'POST', '/admin/users/delete/' . $id, [], true),
            '/admin/users'
        );
        $this->assertSame(0, $this->rows($project, 'users'));
        $this->assertStringContainsString(
            'Users was not found',
            $this->page($project, '/admin/users/edit/999', true)
        );
    }

    public function testAnArrayInTheSearchIsIgnored(): void
    {
        $project = $this->scaffolded('ArraySearch', 'users', ScaffoldUsersMigration::class);

        $this->insertUser($project, 'ann');
        $this->insertUser($project, 'bob');

        foreach (['/users/search?name[]=x', '/users/search?age[]=5'] as $uri) {
            $content = $this->page($project, $uri);

            $this->assertStringContainsString('<td>ann</td>', $content);
            $this->assertStringContainsString('<td>bob</td>', $content);
        }
    }

    public function testAnEditChangesTheRow(): void
    {
        $project = $this->scaffolded('Edit', 'users', ScaffoldUsersMigration::class);
        $id      = $this->insertUser($project, 'ann');
        $content = $this->page($project, '/users/edit/' . $id);

        $this->assertStringContainsString('action="/users/save/' . $id . '"', $content);
        $this->assertStringContainsString('href="/users"', $content);
        $this->assertStringContainsString('value="ann"', $content);

        $this->assertRedirect($this->request($project, 'POST', '/users/save/' . $id, ['name' => 'bob']), '/users');
        $this->assertSame('bob', $this->value($project, 'SELECT name FROM users WHERE id = ' . $id));
    }

    public function testAnEmptyNumberStoresNull(): void
    {
        $project = $this->scaffolded('EmptyNumber', 'users', ScaffoldUsersMigration::class);

        $this->assertRedirect(
            $this->request($project, 'POST', '/users/create', ['name' => 'ann', 'age' => '']),
            '/users'
        );
        $this->assertNull($this->value($project, 'SELECT age FROM users'));
    }

    public function testANewRowGoesToTheList(): void
    {
        $project = $this->scaffolded('NewRow', 'users', ScaffoldUsersMigration::class);
        $content = $this->page($project, '/users/new');

        $this->assertStringContainsString('action="/users/create"', $content);
        $this->assertStringContainsString('name="name"', $content);
        $this->assertStringNotContainsString('name="id"', $content);

        $this->assertRedirect(
            $this->request($project, 'POST', '/users/create', ['name' => 'ann', 'age' => '30']),
            '/users'
        );
        $this->assertSame('ann', $this->value($project, 'SELECT name FROM users'));
        $this->assertSame(30, $this->number($project, 'SELECT age FROM users'));
    }

    public function testAPageOutOfRangeShowsNoRows(): void
    {
        $project = $this->scaffolded('OutOfRange', 'users', ScaffoldUsersMigration::class);

        $this->insertUser($project, 'ann');

        $this->assertStringContainsString('No rows.', $this->page($project, '/users?page=99'));
    }

    public function testAPageThatIsNotANumberShowsTheFirstPage(): void
    {
        $project = $this->scaffolded('PageWord', 'users', ScaffoldUsersMigration::class);

        $this->insertUser($project, 'ann');

        $this->assertStringContainsString('<td>ann</td>', $this->page($project, '/users?page=abc'));
    }

    public function testARefusedValueShowsTheEditFormAgain(): void
    {
        $project = $this->scaffolded('RefusedEdit', 'users', ScaffoldUsersMigration::class);
        $id      = $this->insertUser($project, 'ann');
        $content = $this->content(
            $this->request($project, 'POST', '/users/save/' . $id, ['name' => '', 'age' => '7'])
        );

        $this->assertStringContainsString('name is required', $content);
        $this->assertStringContainsString('value="7"', $content);
        $this->assertStringContainsString('action="/users/save/' . $id . '"', $content);
        $this->assertSame('ann', $this->value($project, 'SELECT name FROM users'));
        $this->assertNull($this->value($project, 'SELECT age FROM users'));
    }

    public function testARefusedValueShowsTheNewFormAgain(): void
    {
        $project = $this->scaffolded('RefusedNew', 'users', ScaffoldUsersMigration::class);
        $content = $this->content(
            $this->request($project, 'POST', '/users/create', ['name' => '', 'age' => '7'])
        );

        $this->assertStringContainsString('name is required', $content);
        $this->assertStringContainsString('value="7"', $content);
        $this->assertStringContainsString('action="/users/create"', $content);
        $this->assertSame(0, $this->rows($project, 'users'));
    }

    public function testARowThatIsNotFoundShowsAMessageAndTheList(): void
    {
        $project = $this->scaffolded('NotFound', 'users', ScaffoldUsersMigration::class);

        foreach (
            [
                ['GET', '/users/edit/999'],
                ['GET', '/users/edit'],
                ['POST', '/users/save/999'],
                ['POST', '/users/delete/999'],
            ] as [$method, $uri]
        ) {
            $content = $this->content($this->request($project, $method, $uri));

            $this->assertStringContainsString('Users was not found', $content);
            $this->assertStringContainsString('action="/users/search"', $content);
        }
    }

    public function testAStringKeyWithASpaceWorks(): void
    {
        $project = $this->scaffolded('StringKey', 'countries', ScaffoldCountriesMigration::class);

        $this->assertStringContainsString('name="code"', $this->page($project, '/countries/new'));
        $this->assertRedirect(
            $this->request($project, 'POST', '/countries/create', ['code' => 'a b', 'name' => 'x']),
            '/countries'
        );
        $this->assertStringContainsString('/countries/edit/a%20b', $this->page($project, '/countries'));
        $this->assertStringNotContainsString('name="code"', $this->page($project, '/countries/edit/a%20b'));
        $this->assertRedirect(
            $this->request($project, 'POST', '/countries/save/a%20b', ['name' => 'y']),
            '/countries'
        );
        $this->assertSame('y', $this->value($project, "SELECT name FROM countries WHERE code = 'a b'"));
        $this->assertRedirect($this->request($project, 'POST', '/countries/delete/a%20b'), '/countries');
        $this->assertSame(0, $this->rows($project, 'countries'));
    }

    public function testATableWithCamelCasePropertiesWorksOnEachPage(): void
    {
        $project = $this->scaffolded('CamelCase', 'user_profiles', ScaffoldProfilesMigration::class, true);

        $this->assertStringContainsString('First name', $this->page($project, '/user-profiles'));
        $this->assertRedirect(
            $this->request($project, 'POST', '/user-profiles/create', ['firstName' => 'ann', 'age' => '3']),
            '/user-profiles'
        );
        $this->assertSame('ann', $this->value($project, 'SELECT first_name FROM user_profiles'));
        $this->assertStringContainsString(
            '<td>ann</td>',
            $this->page($project, '/user-profiles/search?firstName=ann')
        );

        $id = $this->number($project, 'SELECT id FROM user_profiles');

        $this->assertRedirect(
            $this->request($project, 'POST', '/user-profiles/save/' . $id, ['firstName' => 'bob']),
            '/user-profiles'
        );
        $this->assertSame('bob', $this->value($project, 'SELECT first_name FROM user_profiles'));
        $this->assertStringNotContainsString(
            '<td>bob</td>',
            $this->page($project, '/user-profiles/search?firstName=ann')
        );
    }

    public function testAValueIsEscapedInTheList(): void
    {
        $project = $this->scaffolded('Escaped', 'users', ScaffoldUsersMigration::class);

        $this->insertUser($project, '<script>alert(1)</script>');

        $content = $this->page($project, '/users');

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString('<script>alert', $content);
    }

    public function testAWhitespaceOnlyInputStoresNull(): void
    {
        $project = $this->scaffolded('Whitespace', 'users', ScaffoldUsersMigration::class);

        $this->assertRedirect(
            $this->request($project, 'POST', '/users/create', ['name' => 'ann', 'age' => ' ', 'email' => '  ']),
            '/users'
        );
        $this->assertNull($this->value($project, 'SELECT age FROM users'));
        $this->assertNull($this->value($project, 'SELECT email FROM users'));
    }

    public function testSearchShowsOnlyTheRowsThatMatch(): void
    {
        $project = $this->scaffolded('Search', 'users', ScaffoldUsersMigration::class);

        $this->insertUser($project, 'ann');
        $this->insertUser($project, 'bob');

        $content = $this->page($project, '/users/search?name=ann');

        $this->assertStringContainsString('<td>ann</td>', $content);
        $this->assertStringNotContainsString('<td>bob</td>', $content);
        $this->assertStringContainsString('value="ann"', $content);
    }

    public function testTheListOfAnEmptyTableHasALine(): void
    {
        $project = $this->scaffolded('Empty', 'users', ScaffoldUsersMigration::class);

        $this->assertStringContainsString('No rows.', $this->page($project, '/users'));
    }

    public function testTheListShowsTenRowsAndTheSearchForm(): void
    {
        $project = $this->scaffolded('List', 'users', ScaffoldUsersMigration::class);
        $ids     = [];

        for ($row = 1; $row <= 12; $row++) {
            $ids[] = $this->insertUser($project, 'user ' . $row);
        }

        $first = $this->page($project, '/users');

        $this->assertSame(10, substr_count($first, '/users/edit/'));
        $this->assertStringContainsString('method="get"', $first);
        $this->assertStringContainsString('action="/users/search"', $first);
        $this->assertStringContainsString('/users/search?page=2', $first);
        $this->assertStringNotContainsString('Previous', $first);
        // The order of the key.
        $this->assertStringContainsString('/users/edit/' . $ids[0] . '"', $first);
        $this->assertStringNotContainsString('/users/edit/' . $ids[10] . '"', $first);

        $second = $this->page($project, '/users/search?page=2');

        $this->assertSame(2, substr_count($second, '/users/edit/'));
        $this->assertStringContainsString('Previous', $second);
        $this->assertStringContainsString('/users/edit/' . $ids[10] . '"', $second);
    }

    public function testTheSecondPageKeepsTheSearchValues(): void
    {
        $project = $this->scaffolded('SearchPage', 'users', ScaffoldUsersMigration::class);

        for ($row = 1; $row <= 12; $row++) {
            $this->insertUser($project, 'ann ' . $row);
        }

        $this->insertUser($project, 'bob');

        $this->assertStringContainsString(
            '/users/search?name=ann&page=2',
            $this->page($project, '/users/search?name=ann')
        );
        $this->assertSame(
            2,
            substr_count($this->page($project, '/users/search?name=ann&page=2'), '/users/edit/')
        );
    }

    public function testTheTableOfASchemaWorksOnEachPage(): void
    {
        // models.schema (#69): the model reads the table of the schema. The
        // default schema has no table vendors, so a model with no
        // setSchema() fails.
        if ('sqlite' === $this->getDriver()) {
            $this->markTestSkipped('SQLite has no schemas');
        }

        $this->createSchema();

        $project = $this->project('Schema', BillingVendorsMigration::class);
        $file    = $project . '/crest.php';
        $table   = BillingMigration::SCHEMA . '.vendors';

        file_put_contents(
            $file,
            str_replace(
                "        'classes'   => [],\n",
                "        'classes'   => [],\n        'schema'    => '" . BillingMigration::SCHEMA . "',\n",
                (string) file_get_contents($file)
            )
        );

        $this->assertSame(
            0,
            $this->runThroughKernel('make:scaffold', ScaffoldCommand::class, ['vendors', '--directory', $project]),
            $this->readStderr()
        );
        $this->assertRedirect($this->request($project, 'POST', '/vendors/create', ['name' => 'acme']), '/vendors');
        $this->assertSame('acme', $this->value($project, 'SELECT name FROM ' . $table));
        $this->assertStringContainsString('<td>acme</td>', $this->page($project, '/vendors'));

        $id = $this->number($project, 'SELECT id FROM ' . $table);

        $this->assertRedirect(
            $this->request($project, 'POST', '/vendors/save/' . $id, ['name' => 'bolt']),
            '/vendors'
        );
        $this->assertSame('bolt', $this->value($project, 'SELECT name FROM ' . $table));
        $this->assertRedirect($this->request($project, 'POST', '/vendors/delete/' . $id), '/vendors');
        $this->assertSame(0, $this->rows($project, $table));
    }

    private function assertRedirect(ResponseInterface $response, string $location): void
    {
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($location, $response->getHeaders()->get('Location'));
    }

    /**
     * The content of a page: no status is 200, because the application sets
     * none when it succeeds.
     */
    private function content(ResponseInterface $response): string
    {
        $this->assertContains($response->getStatusCode(), [null, 200]);

        return (string) $response->getContent();
    }

    /**
     * Adds a row to users, and gives its key. Not lastInsertId(): PostgreSQL
     * needs the name of the sequence for it.
     */
    private function insertUser(string $project, string $name): int
    {
        $pdo       = $this->pdo($project);
        $statement = $pdo->prepare('INSERT INTO users (name) VALUES (:name)');

        $statement->execute(['name' => $name]);

        $find = $pdo->prepare('SELECT id FROM users WHERE name = :name');

        $find->execute(['name' => $name]);

        return (int) $find->fetchColumn();
    }

    /**
     * The first column of the first row of a query, as an integer. -1 when it
     * is not a number, so that an assertion on the value fails.
     */
    private function number(string $project, string $sql): int
    {
        $value = $this->value($project, $sql);

        return true === is_numeric($value) ? (int) $value : -1;
    }

    private function page(string $project, string $uri, bool $modules = false): string
    {
        return $this->content($this->request($project, 'GET', $uri, [], $modules));
    }

    /**
     * The connection of the test to the database of the project: its SQLite
     * file, or the database of the suite.
     */
    private function pdo(string $project): PDO
    {
        if ('sqlite' === $this->getDriver()) {
            return new PDO('sqlite:' . $project . '/storage/database.sqlite');
        }

        return $this->getConnection()->getPdo();
    }

    /**
     * A project with the database of the driver, and the table of the
     * migration.
     *
     * @param class-string<AbstractSchema> $migration
     */
    private function project(string $case, string $migration): string
    {
        $driver  = $this->getDriver();
        $project = $this->generate('mvc', $driver, 'Generated\\Scaffold\\' . $case);

        if ('sqlite' !== $driver) {
            $this->databaseValues(true, false);
        }

        (new $migration($this->pdo($project), false))->create();

        return $project;
    }

    /**
     * Sends a request through the generated application, as public/index.php
     * does, but in this process and with no send().
     *
     * With $modules, the application registers the module admin and its
     * routes, as the guidance of make:module says.
     *
     * @param array<string, string> $post
     */
    private function request(
        string $project,
        string $method,
        string $uri,
        array $post = [],
        bool $modules = false
    ): ResponseInterface {
        Di::reset();

        $query = (string) parse_url($uri, PHP_URL_QUERY);
        $get   = [];

        parse_str($query, $get);

        $_GET                      = $get;
        $_POST                     = $post;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_SERVER['PHP_SELF']       = (string) parse_url($uri, PHP_URL_PATH);

        $config = Config::discover($project);
        $class  = $config->bootstrap();

        if (null === $class) {
            $this->fail('crest.php names no front controller');
        }

        $front = new $class($project);

        if (false === method_exists($front, 'boot')) {
            $this->fail(sprintf('%s has no boot()', $class));
        }

        $container = $front->boot();

        if (false === $container instanceof DiInterface) {
            $this->fail('boot() did not return a DI container');
        }

        $application = new Application($container);

        if (true === $modules) {
            $router = $container->getShared('router');

            if (false === $router instanceof Router) {
                $this->fail('the router of the project is not a Phalcon\Mvc\Router');
            }

            $application->registerModules(
                ['admin' => ['className' => $config->namespace() . '\\Modules\\Admin\\Module']]
            );
            $router->add('/admin', ['module' => 'admin', 'controller' => 'index', 'action' => 'index']);
            $router->add('/admin/:controller', ['module' => 'admin', 'controller' => 1, 'action' => 'index']);
            $router->add(
                '/admin/:controller/:action/:params',
                ['module' => 'admin', 'controller' => 1, 'action' => 2, 'params' => 3]
            );
        }

        $response = $application->handle($uri);

        if (false === $response instanceof ResponseInterface) {
            $this->fail('the application did not return a response');
        }

        return $response;
    }

    /**
     * The number of rows of a table.
     */
    private function rows(string $project, string $table): int
    {
        return $this->number($project, 'SELECT COUNT(*) FROM ' . $table);
    }

    /**
     * A project with the pages of a table.
     *
     * @param class-string<AbstractSchema> $migration
     */
    private function scaffolded(string $case, string $table, string $migration, bool $camelCase = false): string
    {
        $project = $this->project($case, $migration);

        if (true === $camelCase) {
            $file = $project . '/crest.php';

            file_put_contents(
                $file,
                str_replace("'camelCase' => false", "'camelCase' => true", (string) file_get_contents($file))
            );
        }

        $this->assertSame(
            0,
            $this->runThroughKernel('make:scaffold', ScaffoldCommand::class, [$table, '--directory', $project]),
            $this->readStderr()
        );

        return $project;
    }

    /**
     * The first column of the first row of a query, on the connection of the
     * test.
     */
    private function value(string $project, string $sql): mixed
    {
        $statement = $this->pdo($project)->query($sql);

        if (false === $statement) {
            $this->fail('the query failed: ' . $sql);
        }

        return $statement->fetchColumn();
    }
}
