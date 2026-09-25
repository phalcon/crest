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
use Crest\Command\Make\ActionCommand;
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
use Phalcon\ADR\Front\AbstractHttpFront;
use Phalcon\Container\Container;
use Phalcon\Contracts\ADR\Application;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function class_exists;
use function extension_loaded;
use function file_get_contents;
use function interface_exists;
use function is_file;
use function preg_match_all;
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
 * The generated project must run, not only exist.
 *
 * `new` runs nothing, so no other test proves the wiring: crest.php names a
 * front controller that crest can boot, and the front sends GET / to the seed
 * action. Boot alone does not prove the second part:
 * AbstractHttpFront::boot() does not call getApplication(), and
 * getApplication() sets the base namespace and the action directory. Thus
 * the last test sends GET / through the generated application in this
 * process. There is no docker and no HTTP. The full generate, install and
 * request run is a release check.
 *
 * The generated classes are declared in this process. Thus they use a
 * namespace that no other test uses.
 */
final class GeneratedProjectTest extends TestCase
{
    use GeneratesInAScratchProject;

    private const ROOT_NAMESPACE = 'Scaffolded';

    private ?Closure $autoloader = null;

    private string $project = '';

    /** @var array<mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->startScratchProject('generated-project');
        $this->server = $_SERVER;

        if (
            false === PackageVersion::isInstalled('phalcon/phalcon')
            && false === extension_loaded('phalcon')
        ) {
            $this->markTestSkipped('running the generated project needs Phalcon present');
        }

        $this->runProjectCommand('new', NewCommand::class, ['shop', '--namespace', self::ROOT_NAMESPACE]);

        $this->project = $this->root . '/shop';

        // The project has no vendor/ yet. Load its classes as its own
        // composer.json does: the root namespace maps to src/.
        $source = $this->project . '/src/';

        $this->autoloader = static function (string $class) use ($source): void {
            $prefix = self::ROOT_NAMESPACE . '\\';

            if (false === str_starts_with($class, $prefix)) {
                return;
            }

            $file = $source . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (true === is_file($file)) {
                require $file;
            }
        };

        spl_autoload_register($this->autoloader);
    }

    protected function tearDown(): void
    {
        if (null !== $this->autoloader) {
            spl_autoload_unregister($this->autoloader);
        }

        $_SERVER = $this->server;

        $this->endScratchProject();
    }

    public function testCrestBootsTheGeneratedFrontController(): void
    {
        $this->assertInstanceOf(
            Container::class,
            Bootstrap::container(Config::discover($this->project))
        );
    }

    public function testEveryGeneratedPhpFileParsesAndItsImportsResolve(): void
    {
        $files = ['crest.php', '.htrouter.php', 'public/index.php', 'src/AppFront.php', 'src/Action/Get.php'];

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

    public function testTheGeneratedApplicationAnswersGetSlash(): void
    {
        $response = $this->get('/');

        // The content is the message: on failure, the error responder puts
        // the reason there.
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    }

    public function testTheGeneratedApplicationFindsActionsInItsDirectory(): void
    {
        // GET / names a class directly under the base namespace, so the router
        // does not read the action directory for it. A deeper route does: the
        // router goes into src/Action/Hello only when that directory exists.
        $command = new ActionCommand();

        $command->handle(
            new Input(
                'make:action',
                $command->define()
                    ->merge(Kernel::globals())
                    ->bind(['GET', '/hello', '--directory', $this->project])
            ),
            new Output($this->stdout, $this->stderr, false)
        );

        $response = $this->get('/hello');

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    }

    public function testTheGeneratedConfigurationIsReadByCrest(): void
    {
        $config = Config::discover($this->project);

        $this->assertSame(Flavor::ADR, $config->flavor());
        $this->assertSame(self::ROOT_NAMESPACE, $config->namespace());
        $this->assertSame(self::ROOT_NAMESPACE . '\\AppFront', $config->bootstrap());
        $this->assertSame(self::ROOT_NAMESPACE . '\\Action', $config->namespaceFor('action'));
        $this->assertSame($this->project . '/src/Action', $config->path('action'));
    }

    /**
     * Sends a GET request through the generated application, as
     * public/index.php does, but in this process and with no emitter.
     */
    private function get(string $uri): ResponseInterface
    {
        // The class that crest.php names, as Bootstrap reads it. A literal
        // class name here would make PHPStan look for a class that exists only
        // at run time.
        $class = Config::discover($this->project)->bootstrap();

        if (null === $class) {
            $this->fail('crest.php names no front controller');
        }

        $front = new $class($this->project);

        if (false === $front instanceof AbstractHttpFront) {
            $this->fail(sprintf('%s is not a front controller', $class));
        }

        $container   = $front->boot();
        $application = (new ReflectionMethod($front, 'getApplication'))->invoke($front, $container);

        if (false === $application instanceof Application) {
            $this->fail('getApplication() did not return an ADR application');
        }

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;

        $request = $container->get(AttributeRequest::class);

        if (false === $request instanceof AttributeRequest) {
            $this->fail('the container did not supply an AttributeRequest');
        }

        return $application->handle($request);
    }
}
