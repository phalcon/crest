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

namespace Crest\Tests\Unit\Command;

use Closure;
use Crest\Command\Make\ControllerCommand;
use Crest\Command\NewCommand;
use Crest\Console\Input;
use Crest\Console\Kernel;
use Crest\Console\Output;
use Crest\Console\PackageVersion;
use Crest\Project\Bootstrap;
use Crest\Project\Config;
use Crest\Project\Flavor;
use Crest\Tests\Support\GeneratesInAScratchProject;
use ParseError;
use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Application;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

use function class_exists;
use function extension_loaded;
use function file_get_contents;
use function interface_exists;
use function is_array;
use function is_file;
use function method_exists;
use function preg_match_all;
use function putenv;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function token_get_all;

use const TOKEN_PARSE;

/**
 * The generated MVC project must run, not only exist.
 *
 * By default, `new` runs nothing, so no other test proves the wiring:
 * crest.php names a front controller that crest can boot, the providers set
 * the dispatcher and the view, and GET / renders the index view in the
 * layout. This test sends the requests through the generated application in
 * this process. There is no docker and no HTTP. The full generate, install
 * and request run is a release check.
 *
 * A project with a database (#54) runs too: its db service connects at the
 * first use, so GET / works with no database server.
 *
 * The generated classes are declared in this process. Thus they use a
 * namespace that no other test uses. It has two parts, so that each `use`
 * line and the namespace of the dispatcher are tested with a nested
 * namespace. The project with a database has a namespace of its own, because
 * its front is another class.
 */
final class GeneratedMvcProjectTest extends TestCase
{
    use GeneratesInAScratchProject;

    private const DATABASE_NAMESPACE = 'Scaffolded\\MvcWithDatabase';

    private const ROOT_NAMESPACE = 'Scaffolded\\Mvc';

    /** @var list<Closure> */
    private array $autoloaders = [];

    private string $project = '';

    /** @var array<mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->startScratchProject('generated-mvc-project');
        $this->server = $_SERVER;

        if (
            false === PackageVersion::isInstalled('phalcon/phalcon')
            && false === extension_loaded('phalcon')
        ) {
            $this->markTestSkipped('running the generated project needs Phalcon present');
        }

        $this->project = $this->generate('shop', self::ROOT_NAMESPACE, []);
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        // FactoryDefault makes itself the default container. The next test
        // must not get the container of this one. A skipped test has no
        // Phalcon, and no project.
        if ('' !== $this->project) {
            Di::reset();
        }

        $_SERVER = $this->server;

        $this->endScratchProject();
    }

    public function testAControllerFromMakeControllerAnswersItsUrl(): void
    {
        // new and make:controller work together: the controller goes where
        // the dispatcher of the generated project looks for it. It has no view
        // yet, so the response is the layout alone.
        $command = new ControllerCommand();

        $status = $command->handle(
            new Input(
                'make:controller',
                $command->define()
                    ->merge(Kernel::globals())
                    ->bind(['Users', '--directory', $this->project])
            ),
            new Output($this->stdout, $this->stderr, false)
        );

        $this->assertSame(0, $status);
        // new wrote the same base as make:controller: no line for the base.
        $this->assertStringNotContainsString('CrestControllerBase.php', $this->readStdout());

        $content = $this->get('/users');

        $this->assertStringContainsString('<title>shop</title>', $content);
        $this->assertStringNotContainsString('<h1>shop</h1>', $content);
    }

    public function testAProjectWithADatabaseAnswersGetSlashWithNoDatabase(): void
    {
        // The db service connects at the first use, and GET / does not use
        // it. There is no MySQL server here.
        $this->project = $this->generate('shop-db', self::DATABASE_NAMESPACE, ['--database', 'mysql']);

        $this->assertStringContainsString('<h1>shop-db</h1>', $this->get('/'));
    }

    public function testCrestBootsTheGeneratedFrontController(): void
    {
        $this->assertInstanceOf(
            DiInterface::class,
            Bootstrap::container(Config::discover($this->project))
        );
    }

    public function testEveryFileOfAProjectWithADatabaseParsesAndItsImportsResolve(): void
    {
        $this->project = $this->generate('shop-db', self::DATABASE_NAMESPACE, ['--database', 'mysql']);

        $this->assertParsesAndImportsResolve(['crest.php', 'src/AppFront.php', 'src/Providers/DbProvider.php']);
    }

    public function testEveryGeneratedFileParsesAndItsImportsResolve(): void
    {
        $this->assertParsesAndImportsResolve(
            [
                'crest.php',
                '.htrouter.php',
                'public/index.php',
                'src/AppFront.php',
                'src/Controllers/Base/CrestControllerBase.php',
                'src/Controllers/IndexController.php',
                'src/Providers/DispatcherProvider.php',
                'src/Providers/ViewProvider.php',
                'themes/default/index.phtml',
                'themes/default/index/index.phtml',
            ]
        );
    }

    public function testGetSlashShowsTheIndexViewInTheLayout(): void
    {
        $content = $this->get('/');

        $this->assertStringContainsString('<title>shop</title>', $content);
        $this->assertStringContainsString('<h1>shop</h1>', $content);
    }

    public function testGetSlashWithAQueryStringReachesTheIndexController(): void
    {
        $this->assertStringContainsString('<h1>shop</h1>', $this->get('/?page=2'));
    }

    public function testTheDbProviderReadsTheEnvironmentInOrder(): void
    {
        // getenv() wins, then $_ENV, then the default for PHP on the host. An
        // empty value is a value: DB_PASSWORD= gives an empty password. The
        // closure of the db service keeps the values; nothing connects.
        $this->project = $this->generate('shop-db', self::DATABASE_NAMESPACE, ['--database', 'mysql']);

        putenv('DB_HOST=from-getenv');
        putenv('DB_PASSWORD=');
        $_ENV['DB_HOST'] = 'from-env';
        $_ENV['DB_NAME'] = 'from-env';

        try {
            $config = $this->dbConfig();
        } finally {
            putenv('DB_HOST');
            putenv('DB_PASSWORD');
            unset($_ENV['DB_HOST'], $_ENV['DB_NAME']);
        }

        $this->assertSame(
            [
                'host'     => 'from-getenv',
                'port'     => '3306',
                'dbname'   => 'from-env',
                'username' => 'shop_db',
                'password' => '',
                'charset'  => 'utf8mb4',
            ],
            $config
        );
    }

    public function testTheGeneratedConfigurationIsReadByCrest(): void
    {
        $config = Config::discover($this->project);

        $this->assertSame(Flavor::MVC, $config->flavor());
        $this->assertSame(self::ROOT_NAMESPACE, $config->namespace());
        $this->assertSame(self::ROOT_NAMESPACE . '\\AppFront', $config->bootstrap());
        $this->assertSame(self::ROOT_NAMESPACE . '\\Controllers', $config->namespaceFor('controller'));
        $this->assertSame($this->project . '/src/Controllers', $config->path('controller'));
        $this->assertSame($this->project . '/src/Providers', $config->path('provider'));
        $this->assertSame($this->project . '/themes/default', $config->path('views'));
    }

    /**
     * Each file parses, and each class that it imports exists.
     *
     * @param list<string> $files
     */
    private function assertParsesAndImportsResolve(array $files): void
    {
        foreach ($files as $path) {
            $code = (string) file_get_contents($this->project . '/' . $path);

            try {
                $this->assertNotEmpty(token_get_all($code, TOKEN_PARSE));
            } catch (ParseError $error) {
                $this->fail(sprintf('%s does not parse: %s', $path, $error->getMessage()));
            }

            preg_match_all('/^use\s+(?!function\s|const\s)([\w\\\\]+)/m', $code, $matches);

            foreach ($matches[1] as $import) {
                $this->assertTrue(
                    class_exists($import) || interface_exists($import),
                    sprintf('%s imports %s, which does not exist', $path, $import)
                );
            }
        }
    }

    /**
     * The connection values of the db service: the $config of its closure.
     * The provider reads the environment when the front boots.
     *
     * @return array<mixed>
     */
    private function dbConfig(): array
    {
        $container = Bootstrap::container(Config::discover($this->project));

        if (false === $container instanceof DiInterface) {
            $this->fail('boot() did not return a DI container');
        }

        $definition = $container->getService('db')->getDefinition();

        if (false === $definition instanceof Closure) {
            $this->fail('the db service is not a closure');
        }

        $config = (new ReflectionFunction($definition))->getStaticVariables()['config'] ?? null;

        if (false === is_array($config)) {
            $this->fail('the closure of the db service has no $config');
        }

        return $config;
    }

    /**
     * Runs `new` for an MVC project in the scratch folder, and loads its
     * classes as its own composer.json does: the root namespace maps to
     * src/. The project has no vendor/ yet.
     *
     * @param list<string> $options
     */
    private function generate(string $name, string $namespace, array $options): string
    {
        $this->runProjectCommand(
            'new',
            NewCommand::class,
            [$name, '--flavor', 'mvc', '--namespace', $namespace, ...$options]
        );

        $source     = $this->root . '/' . $name . '/src/';
        $autoloader = static function (string $class) use ($namespace, $source): void {
            $prefix = $namespace . '\\';

            if (false === str_starts_with($class, $prefix)) {
                return;
            }

            $file = $source . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (true === is_file($file)) {
                require $file;
            }
        };

        spl_autoload_register($autoloader);

        $this->autoloaders[] = $autoloader;

        return $this->root . '/' . $name;
    }

    /**
     * Sends a GET request through the generated application, as
     * public/index.php does, but in this process and with no send(). Gives
     * the content of the response.
     */
    private function get(string $uri): string
    {
        // The class that crest.php names, as Bootstrap reads it. A literal
        // class name here would make PHPStan look for a class that exists only
        // at run time.
        $class = Config::discover($this->project)->bootstrap();

        if (null === $class) {
            $this->fail('crest.php names no front controller');
        }

        $front = new $class($this->project);

        if (false === method_exists($front, 'boot')) {
            $this->fail(sprintf('%s has no boot()', $class));
        }

        $container = $front->boot();

        if (false === $container instanceof DiInterface) {
            $this->fail('boot() did not return a DI container');
        }

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;

        $response = (new Application($container))->handle($uri);

        if (false === $response instanceof ResponseInterface) {
            $this->fail('the application did not return a response');
        }

        // No status is 200: the application sets none when it succeeds.
        $this->assertContains($response->getStatusCode(), [null, 200]);

        return (string) $response->getContent();
    }
}
