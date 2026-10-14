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

use Crest\Command\Make\ControllerCommand;
use Crest\Command\Make\MiddlewareCommand;
use Crest\Command\Make\ProviderCommand;
use Crest\Console\PackageVersion;
use Crest\Paths;
use Crest\Tests\Support\GeneratesInAScratchProject;
use Phalcon\ADR\Front\AbstractHttpFront;
use Phalcon\ADR\Router\Router;
use Phalcon\Container\Container;
use Phalcon\Di\Di;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Mvc\Dispatcher;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function extension_loaded;
use function file_get_contents;
use function sprintf;

/**
 * make:middleware, make:provider and make:controller print wiring the
 * developer copies into their project, and that wiring names framework
 * members crest holds no other reference to. Nothing otherwise links the
 * printed text to the API it describes, so a rename leaves crest confidently
 * instructing someone to write something that does not work - and the failure
 * lands on the developer, who has no reason to suspect the tool that told them
 * what to type.
 *
 * Both halves are pinned here: the command still prints the call, and the
 * framework still has the member the call names.
 */
final class GuidanceContractsTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('make-guidance', 'src/Middleware', 'src/Provider');

        if (
            false === PackageVersion::isInstalled('phalcon/phalcon')
            && false === extension_loaded('phalcon')
        ) {
            $this->markTestSkipped('verifying the printed wiring needs Phalcon present');
        }
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'one word' => ['Users', 'users'];
        yield 'two words' => ['UserProfiles', 'user-profiles'];
        yield 'an acronym' => ['APIKeys', 'a-p-i-keys'];
        yield 'a name outside ASCII' => ['Ünits', 'Ünits'];
    }

    public function testControllerGuidanceNamesADispatcherMethodThatExists(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->runProjectCommand('make:controller', ControllerCommand::class, ['Users']);

        $this->assertStringContainsString(
            "\$dispatcher->setDefaultNamespace('App\\Controllers');",
            $this->readStdout()
        );

        $this->assertSame(['string'], $this->parameterTypes(Dispatcher::class, 'setDefaultNamespace'));
    }

    public function testMiddlewareGuidanceNamesARouterMethodThatExists(): void
    {
        $this->runProjectCommand('make:middleware', MiddlewareCommand::class, ['Auth']);

        $this->assertStringContainsString('setMiddlewareMap(', $this->readStdout());

        $this->assertSame(['array'], $this->parameterTypes(Router::class, 'setMiddlewareMap'));
    }

    public function testMvcProviderGuidanceNamesMethodsThatExist(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->runProjectCommand('make:provider', ProviderCommand::class, ['Cache']);

        $stdout = $this->readStdout();

        $this->assertStringContainsString('providers()', $stdout);
        $this->assertStringContainsString('$di->register(', $stdout);

        // DiInterface has no register(). Di declares it, and FactoryDefault
        // extends Di.
        $this->assertSame([ServiceProviderInterface::class], $this->parameterTypes(Di::class, 'register'));

        // The front controller of crest new has the method that the first
        // line of the guidance names.
        $this->assertStringContainsString(
            'private function providers(): array',
            (string) file_get_contents(Paths::stubs() . '/mvc/project-front.stub')
        );
    }

    public function testProviderGuidanceNamesAFrontMethodThatExists(): void
    {
        $this->runProjectCommand('make:provider', ProviderCommand::class, ['Cache']);

        $stdout = $this->readStdout();

        $this->assertStringContainsString('registerProviders(', $stdout);
        $this->assertStringContainsString('parent::registerProviders(', $stdout);

        $this->assertSame(
            [Container::class],
            $this->parameterTypes(AbstractHttpFront::class, 'registerProviders')
        );
    }

    public function testTheParentCallHasAnImplementationToReach(): void
    {
        // "Keep the parent call: it is what registers the ADR services" holds
        // only while the parent declares a concrete body of its own. Abstract,
        // or declared somewhere further up, and the printed advice is wrong.
        $method = new ReflectionMethod(AbstractHttpFront::class, 'registerProviders');

        $this->assertFalse($method->isAbstract());
        $this->assertSame(AbstractHttpFront::class, $method->getDeclaringClass()->getName());
    }

    /**
     * crest makes the route with its own copy of the rule of the dispatcher.
     * The real dispatcher must give the class of the controller again from
     * the route that the guidance prints.
     *
     * @dataProvider routes
     */
    public function testTheRouteOfTheGuidanceGivesTheControllerAgain(string $name, string $route): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->runProjectCommand('make:controller', ControllerCommand::class, [$name]);

        $this->assertStringContainsString('GET /' . $route . ' runs indexAction()', $this->readStdout());

        $dispatcher = new Dispatcher();
        $dispatcher->setDefaultNamespace('App\\Controllers');
        $dispatcher->setControllerName($route);

        $this->assertSame('App\\Controllers\\' . $name . 'Controller', $dispatcher->getHandlerClass());
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function parameterTypes(string $class, string $method): array
    {
        $reflection = new ReflectionClass($class);

        // Checked here rather than with method_exists() at the call site: with a
        // literal class and method the analyzer folds that to a constant true,
        // so the assertion that was meant to catch a rename never ran.
        if (false === $reflection->hasMethod($method)) {
            $this->fail(
                sprintf('%s no longer declares %s(), which crest prints as guidance', $class, $method)
            );
        }

        $types = [];

        foreach ($reflection->getMethod($method)->getParameters() as $parameter) {
            $type = $parameter->getType();

            $types[] = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
        }

        return $types;
    }
}
