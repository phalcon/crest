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

namespace Crest\Tests\Unit\Generator;

use Crest\Attribute\PropertyName;
use Crest\Attribute\RelationAlias;
use Crest\Console\PackageVersion;
use Crest\Generator\PropertyNames;
use Crest\Generator\RelationAliases;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Tests\Support\ScratchDirectory;
use ParseError;
use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\ADR\Handler;
use Phalcon\Contracts\ADR\Middleware;
use Phalcon\Contracts\ADR\Payload\Payload;
use Phalcon\Contracts\ADR\Responder\Responder;
use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\Contracts\Container\Service\Provider;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

use function class_exists;
use function enum_exists;
use function extension_loaded;
use function file_put_contents;
use function get_parent_class;
use function interface_exists;
use function method_exists;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function token_get_all;
use function trait_exists;

use const TOKEN_PARSE;

/**
 * The packaged stubs name framework classes as text, and nothing else in the
 * suite reads a stub as code. Without these tests a rename on the framework side
 * ships a generator that writes a file the project cannot autoload, while every
 * other assertion still passes.
 */
final class StubContractsTest extends TestCase
{
    use ScratchDirectory;

    /**
     * The flavors that have packaged stubs of their own. CLI has only the
     * shared stubs, and the list of each other flavor has them.
     */
    private const FLAVORS = ['adr', 'mvc'];

    /**
     * Every placeholder the packaged stubs declare, as one superset. A stub that
     * gains a placeholder missing from this map fails the
     * no-placeholder-left assertion rather than quietly emitting `{{ name }}`.
     */
    private const REPLACEMENTS = [
        'alias'         => 'Generated',
        'aliases'       => '',
        'aliasNote'     => '',
        'attributes'    => '',
        'base'          => 'GeneratedBase',
        'baseNamespace' => 'App\\Generated\\Base',
        'class'         => 'GeneratedArtifact',
        'classJson'     => 'App\\\\Generated\\\\GeneratedArtifact',
        'column'        => 'id',
        'columnMap'     => '',
        'command'       => 'generated',
        'entries'       => '',
        'extraKey'      => 'crest',
        'fields'        => "'id'",
        'name'          => 'id',
        'namespace'     => 'App\\Generated',
        'pairs'         => '',
        'params'        => '',
        'properties'    => '',
        'property'      => 'id',
        'referenced'    => "'id'",
        'relations'     => '',
        'route'         => 'generated',
        'table'         => 'generated',
        'template'      => 'generated/index',
        'type'          => 'int',
    ];

    protected function setUp(): void
    {
        if (
            false === PackageVersion::isInstalled('phalcon/phalcon')
            && false === extension_loaded('phalcon')
        ) {
            $this->markTestSkipped('resolving the stub imports needs Phalcon present');
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fragmentStubs(): iterable
    {
        foreach (self::packagedNames() as $key => [$flavor, $name]) {
            if (true === str_starts_with($name, Stub::FRAGMENT_PREFIX)) {
                yield $key => [$flavor, $name];
            }
        }
    }

    /**
     * The contract each stub declares it implements, and the parameter types the
     * generated method signature commits to.
     *
     * Resolving the import is not enough: a contract can keep its name and
     * change its shape, and a generated class whose signature no longer matches
     * the interface it declares is a fatal at declaration time - in the user's
     * project, not here.
     *
     * @return iterable<string, array{class-string, string, list<string>}>
     */
    public static function implementedContracts(): iterable
    {
        yield 'action' => [Action::class, '__invoke', [AttributeRequest::class]];

        yield 'middleware' => [Middleware::class, '__invoke', [AttributeRequest::class, Handler::class]];

        yield 'mvc provider' => [ServiceProviderInterface::class, 'register', [DiInterface::class]];

        yield 'provider' => [Provider::class, 'provide', [Collection::class]];

        yield 'responder' => [
            Responder::class,
            '__invoke',
            [RequestInterface::class, ResponseInterface::class, Payload::class],
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function packagedStubs(): iterable
    {
        foreach (self::packagedNames() as $key => [$flavor, $name]) {
            // The project stubs render a whole application, not one artifact
            // class. ProjectStubsTest holds their contract. A fragment is not
            // a whole class: fragmentStubs() holds its contract.
            if (
                true === str_starts_with($name, Stub::PROJECT_PREFIX)
                || true === str_starts_with($name, Stub::FRAGMENT_PREFIX)
            ) {
                continue;
            }

            yield $key => [$flavor, $name];
        }
    }

    /**
     * Every packaged stub that a project of one of FLAVORS renders: the flavor
     * folders and the shared folder, as the lookup sees them. The key is
     * `<folder>/<name>`, so each stub is in the list one time, also a shared
     * stub that each flavor finds.
     *
     * @return array<string, array{string, string}> The flavor and the name
     */
    private static function packagedNames(): array
    {
        $stubs = [];

        foreach (self::FLAVORS as $flavor) {
            foreach ((new Stub(Paths::stubs()))->packagedFolders($flavor) as $name => $folder) {
                $stubs[$folder . '/' . $name] = [$flavor, $name];
            }
        }

        return $stubs;
    }

    /**
     * @dataProvider fragmentStubs
     */
    public function testAFragmentLeavesNoPlaceholder(string $flavor, string $name): void
    {
        $this->assertFalse(
            str_contains($this->render($flavor, $name), '{{'),
            sprintf("fragment '%s' left a placeholder unrendered", $name)
        );
    }

    /**
     * The assertion that would have caught a framework rename. Alias forms are
     * covered: the capture stops before ` as `, so `Middleware as
     * MiddlewareContract` is checked as the class it actually names.
     *
     * An import in the namespace of the generated code names a class that
     * crest also generates, for example the base of a controller. It does not
     * exist here. testTheMvcControllerExtendsTheFrameworkController() holds
     * its contract.
     *
     * @dataProvider packagedStubs
     */
    public function testEveryImportResolves(string $flavor, string $name): void
    {
        $rendered = $this->render($flavor, $name);

        preg_match_all('/^use\s+(?!function\s|const\s)([\w\\\\]+)/m', $rendered, $matches);

        $this->assertNotEmpty($matches[1], sprintf("stub '%s' imports nothing", $name));

        foreach ($matches[1] as $import) {
            if (true === str_starts_with($import, self::REPLACEMENTS['namespace'] . '\\')) {
                continue;
            }

            $this->assertTrue(
                class_exists($import)
                || interface_exists($import)
                || trait_exists($import)
                || enum_exists($import),
                sprintf("stub '%s' imports %s, which does not exist", $name, $import)
            );
        }
    }

    /**
     * @param class-string $contract
     * @param list<string> $expected
     *
     * @dataProvider implementedContracts
     */
    public function testImplementedContractSignaturesAreUnchanged(
        string $contract,
        string $method,
        array $expected
    ): void {
        $actual = [];

        foreach ((new ReflectionMethod($contract, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();

            $actual[] = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
        }

        $this->assertSame(
            $expected,
            $actual,
            sprintf('%s::%s() changed shape; the stub that implements it has not', $contract, $method)
        );
    }

    /**
     * @dataProvider packagedStubs
     */
    public function testNoPlaceholderIsLeftUnrendered(string $flavor, string $name): void
    {
        $this->assertFalse(
            str_contains($this->render($flavor, $name), '{{'),
            sprintf("stub '%s' left a placeholder unrendered", $name)
        );
    }

    /**
     * @dataProvider packagedStubs
     */
    public function testRendersToParseablePhp(string $flavor, string $name): void
    {
        $rendered = $this->render($flavor, $name);

        try {
            // TOKEN_PARSE is what makes this a syntax check rather than a
            // tokenizer run: without it a malformed stub tokenizes happily.
            $this->assertNotEmpty(token_get_all($rendered, TOKEN_PARSE));
        } catch (ParseError $error) {
            $this->fail(
                sprintf("stub '%s' does not render to valid PHP: %s", $name, $error->getMessage())
            );
        }

        // Proves the class placeholder actually landed, rather than the file
        // merely happening to parse.
        $this->assertStringContainsString('class GeneratedArtifact', $rendered);
    }

    public function testTheAttributeFragmentCallsAMethodOfTheFramework(): void
    {
        // fragment-action-attribute writes $request->getAttributes()->get().
        $type = (new ReflectionMethod(AttributeRequest::class, 'getAttributes'))->getReturnType();

        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertTrue(
            method_exists($type->getName(), 'get'),
            sprintf('%s has no get(), which fragment-action-attribute calls', $type->getName())
        );
    }

    /**
     * The controller stub imports only its base, a generated class, so no
     * other test checks what the two classes extend. Both are loaded here: a
     * rename of Phalcon\Mvc\Controller, or a base stub that no longer extends
     * it, fails here and not in the project of the developer.
     */
    public function testTheMvcControllerExtendsTheFrameworkController(): void
    {
        $namespace = 'Crest\\Tests\\Generated\\Mvc';
        $stub      = new Stub(Paths::stubs());

        $this->makeScratchDirectory('stub-contracts');

        try {
            file_put_contents(
                $this->root . '/Base.php',
                $stub->render(
                    'mvc',
                    'controller-base',
                    ['class' => 'GeneratedBase', 'namespace' => $namespace . '\\Base']
                )
            );
            file_put_contents(
                $this->root . '/Controller.php',
                $stub->render(
                    'mvc',
                    'controller',
                    [
                        'base'          => 'GeneratedBase',
                        'baseNamespace' => $namespace . '\\Base',
                        'class'         => 'GeneratedController',
                        'namespace'     => $namespace,
                    ]
                )
            );

            require $this->root . '/Base.php';
            require $this->root . '/Controller.php';
        } finally {
            $this->removeScratchDirectory();
        }

        $base       = $namespace . '\\Base\\GeneratedBase';
        $controller = $namespace . '\\GeneratedController';

        $this->assertSame(Controller::class, get_parent_class($base));
        $this->assertSame($base, get_parent_class($controller));
        // ReflectionMethod and not method_exists(): the analyzer folds the
        // constant class name, and calls method_exists() always false.
        $this->assertTrue((new ReflectionMethod($controller, 'indexAction'))->isPublic());
    }

    /**
     * The model stub imports only its base, a generated class, so no other
     * test checks what the two classes extend. Both are loaded here: a
     * rename of Phalcon\Mvc\Model, or a base stub that no longer extends it,
     * fails here and not in the project of the developer.
     */
    public function testTheMvcModelExtendsTheFrameworkModel(): void
    {
        $namespace = 'Crest\\Tests\\Generated\\MvcModel';
        $stub      = new Stub(Paths::stubs());
        $relation  = [
            'kind'       => 'belongs-to',
            'fields'     => ['id'],
            'class'      => '\\' . $namespace . '\\GeneratedModel',
            'referenced' => ['id'],
            'alias'      => 'Self',
            'key'        => 'belongsTo generated(id) -> generated(id)',
        ];
        $relations = '';

        // Both relation fragments go into the base. A fragment that does not
        // give valid PHP in initialize() fails the require below.
        foreach (['model-belongs-to', 'model-has-many'] as $fragment) {
            $relations .= $stub->render(
                'mvc',
                Stub::FRAGMENT_PREFIX . $fragment,
                [
                    'alias'      => 'Self',
                    'class'      => '\\' . $namespace . '\\GeneratedModel',
                    'fields'     => "'id'",
                    'referenced' => "'id'",
                ]
            );
        }

        $this->makeScratchDirectory('stub-contracts');

        try {
            file_put_contents(
                $this->root . '/Base.php',
                $stub->render(
                    'mvc',
                    'model-base',
                    [
                        'aliases'    => PropertyNames::attributes(['id' => 'id'])
                            . RelationAliases::attributes([$relation]),
                        'aliasNote'  => PropertyNames::note(['id' => 'id']) . RelationAliases::note([$relation]),
                        'class'      => 'GeneratedModelBase',
                        'columnMap'  => $stub->render(
                            'mvc',
                            Stub::FRAGMENT_PREFIX . 'model-column-map',
                            ['pairs' => "            'id' => 'id',\n"]
                        ),
                        'namespace'  => $namespace . '\\Base',
                        'properties' => "    public ?int \$id = null;\n",
                        'relations'  => $relations,
                        'table'      => 'generated',
                    ]
                )
            );
            file_put_contents(
                $this->root . '/Model.php',
                $stub->render(
                    'mvc',
                    'model',
                    [
                        'base'          => 'GeneratedModelBase',
                        'baseNamespace' => $namespace . '\\Base',
                        'class'         => 'GeneratedModel',
                        'namespace'     => $namespace,
                    ]
                )
            );

            require $this->root . '/Base.php';
            require $this->root . '/Model.php';
        } finally {
            $this->removeScratchDirectory();
        }

        $base  = $namespace . '\\Base\\GeneratedModelBase';
        $model = $namespace . '\\GeneratedModel';

        $this->assertSame(Model::class, get_parent_class($base));
        $this->assertSame($base, get_parent_class($model));
        $this->assertTrue((new ReflectionMethod($base, 'initialize'))->isPublic());

        // The generated attribute matches the constructor of RelationAlias.
        // PHPStan sees a string here, not a class-string, so the test gets
        // the class through initialize(), as the assertion above does.
        $class     = (new ReflectionMethod($base, 'initialize'))->getDeclaringClass();
        $attribute = $class->getAttributes(RelationAlias::class)[0]->newInstance();

        $this->assertSame('belongsTo generated(id) -> generated(id)', $attribute->key);
        $this->assertSame('Self', $attribute->alias);

        // The base has a public columnMap(), and the generated attribute
        // matches the constructor of PropertyName.
        $this->assertTrue((new ReflectionMethod($base, 'columnMap'))->isPublic());

        $property = $class->getAttributes(PropertyName::class)[0]->newInstance();

        $this->assertSame('id', $property->column);
        $this->assertSame('id', $property->name);
    }

    private function render(string $flavor, string $name): string
    {
        return (new Stub(Paths::stubs()))->render($flavor, $name, self::REPLACEMENTS);
    }
}
