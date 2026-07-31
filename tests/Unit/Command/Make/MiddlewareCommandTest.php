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

use Crest\Command\Make\MiddlewareCommand;
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;

use const PHP_EOL;

final class MiddlewareCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('make-middleware', 'src/Middleware');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testAnUnusableNameIsReported(): void
    {
        $status = $this->runCommand(['Admin/Auth']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'Admin/Auth' is not a usable class name",
            $this->readStderr()
        );
    }

    public function testCreatedPathIsReported(): void
    {
        $this->runCommand(['Auth']);

        $this->assertStringContainsString(
            'Created ' . $this->root . '/src/Middleware/AuthMiddleware.php',
            $this->readStdout()
        );
    }

    public function testDefinitionNamesItselfMakeMiddleware(): void
    {
        $this->assertSame('make:middleware', (new MiddlewareCommand())->define()->getName());
    }

    public function testForceOverwritesAnExistingMiddleware(): void
    {
        $this->runCommand(['Auth']);
        file_put_contents($this->root . '/src/Middleware/AuthMiddleware.php', 'stale');

        $status = $this->runCommand(['Auth', '--force']);

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString(
            'stale',
            (string) file_get_contents($this->root . '/src/Middleware/AuthMiddleware.php')
        );
    }

    public function testNameArgumentIsRequired(): void
    {
        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'name'", $this->readStderr());
    }

    public function testRefusesToOverwriteWithoutForce(): void
    {
        $this->runCommand(['Auth']);

        $status = $this->runCommand(['Auth']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('already exists', $this->readStderr());
    }

    public function testTheContractIsAliasedSoTheNameCanNeverCollide(): void
    {
        // `make:middleware Middleware` is the pathological case: the suffix is
        // already there, so the class is named Middleware - and without the
        // alias the stub would emit `implements Middleware` beside
        // `use ...\Middleware;`, which does not compile.
        $status = $this->runCommand(['Middleware']);

        $contents = (string) file_get_contents($this->root . '/src/Middleware/Middleware.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'final class Middleware implements MiddlewareContract',
            $contents
        );
    }

    public function testTheMiddlewareDirectoryIsCreatedWhenItIsAbsent(): void
    {
        $this->safeDeleteDirectory($this->root . '/src/Middleware');

        $status = $this->runCommand(['Auth']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Middleware/AuthMiddleware.php');
    }

    public function testTheRegistrationSnippetIsPrintedWithTheFullClassName(): void
    {
        // The generated class is inert until the router names it, and crest will
        // not edit the bootstrap - so the hint is the whole deliverable and is
        // asserted as one block. Substring checks would let the blank lines that
        // separate the snippet from the prose disappear, and a wall of text is
        // not something anyone pastes from.
        $this->runCommand(['Auth']);

        $expected = 'Created ' . $this->root . '/src/Middleware/AuthMiddleware.php' . PHP_EOL
            . "Nothing runs it yet. Add it to the router's middleware map:" . PHP_EOL
            . PHP_EOL
            . "    \$router->setMiddlewareMap(['' => [\\App\\Middleware\\AuthMiddleware::class]]);"
            . PHP_EOL
            . PHP_EOL
            . "The key is a namespace suffix under the base namespace: '' guards every "
            . "action, '\\Album' only the actions beneath it." . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testTheSuffixIsNotDoubledWhenTheUserSuppliesIt(): void
    {
        $status = $this->runCommand(['AuthMiddleware']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Middleware/AuthMiddleware.php');
        $this->assertFileDoesNotExist($this->root . '/src/Middleware/AuthMiddlewareMiddleware.php');
    }

    public function testTheWholeMiddlewareIsRendered(): void
    {
        // Asserted whole rather than by substring: this is generated code nobody
        // reviews, so a dropped use statement or a mangled signature has to fail
        // here or it ships.
        $status = $this->runCommand(['Auth']);

        $expected = "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\Middleware;\n"
            . "\n"
            . "use Phalcon\Contracts\ADR\Handler;\n"
            . "use Phalcon\Contracts\ADR\Middleware as MiddlewareContract;\n"
            . "use Phalcon\Contracts\Http\AttributeRequest;\n"
            . "use Phalcon\Http\ResponseInterface;\n"
            . "\n"
            . "final class AuthMiddleware implements MiddlewareContract\n"
            . "{\n"
            . "    public function __invoke(AttributeRequest \$request, Handler \$next): ResponseInterface\n"
            . "    {\n"
            . "        return \$next(\$request);\n"
            . "    }\n"
            . "}\n";

        $this->assertSame(0, $status);
        $this->assertSame(
            $expected,
            (string) file_get_contents($this->root . '/src/Middleware/AuthMiddleware.php')
        );
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('make:middleware', MiddlewareCommand::class, $arguments);
    }
}
