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

use Crest\Console\PackageVersion;
use Crest\Generator\Stub;
use Crest\Paths;
use ParseError;
use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\ADR\Handler;
use Phalcon\Contracts\ADR\Middleware;
use Phalcon\Contracts\ADR\Payload\Payload;
use Phalcon\Contracts\ADR\Responder\Responder;
use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\Contracts\Container\Service\Provider;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

use function basename;
use function class_exists;
use function enum_exists;
use function extension_loaded;
use function glob;
use function interface_exists;
use function preg_match_all;
use function sprintf;
use function str_contains;
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
    private const FLAVOR = 'adr';

    /**
     * Every placeholder the packaged stubs declare, as one superset. A stub that
     * gains a placeholder missing from this map fails the
     * no-placeholder-left assertion rather than quietly emitting `{{ name }}`.
     */
    private const REPLACEMENTS = [
        'attributes' => '',
        'class'      => 'GeneratedArtifact',
        'command'    => 'generated',
        'namespace'  => 'App\\Generated',
        'params'     => '',
        'template'   => 'generated/index',
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

        yield 'provider' => [Provider::class, 'provide', [Collection::class]];

        yield 'responder' => [
            Responder::class,
            '__invoke',
            [RequestInterface::class, ResponseInterface::class, Payload::class],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function packagedStubs(): iterable
    {
        $directory = Stub::packagedDirectory(Paths::stubs(), self::FLAVOR);

        foreach (glob($directory . '/*.stub') ?: [] as $file) {
            $name = basename($file, '.stub');

            yield $name => [$name];
        }
    }

    /**
     * The assertion that would have caught a framework rename. Alias forms are
     * covered: the capture stops before ` as `, so `Middleware as
     * MiddlewareContract` is checked as the class it actually names.
     *
     * @dataProvider packagedStubs
     */
    public function testEveryImportResolves(string $name): void
    {
        $rendered = $this->render($name);

        preg_match_all('/^use\s+(?!function\s|const\s)([\w\\\\]+)/m', $rendered, $matches);

        $this->assertNotEmpty($matches[1], sprintf("stub '%s' imports nothing", $name));

        foreach ($matches[1] as $import) {
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
    public function testNoPlaceholderIsLeftUnrendered(string $name): void
    {
        $this->assertFalse(
            str_contains($this->render($name), '{{'),
            sprintf("stub '%s' left a placeholder unrendered", $name)
        );
    }

    /**
     * @dataProvider packagedStubs
     */
    public function testRendersToParseablePhp(string $name): void
    {
        $rendered = $this->render($name);

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

    private function render(string $name): string
    {
        return (new Stub(Paths::stubs()))->render(self::FLAVOR, $name, self::REPLACEMENTS);
    }
}
