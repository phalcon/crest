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

use Crest\Command\Make\ResponderCommand;
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;

final class ResponderCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('make-responder', 'src/Responder');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testAnUnusableNameIsReported(): void
    {
        $status = $this->runCommand(['Admin/Album']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'Admin/Album' is not a usable class name",
            $this->readStderr()
        );
    }

    public function testCreatedPathIsReported(): void
    {
        $this->runCommand(['Album']);

        $this->assertStringContainsString(
            'Created ' . $this->root . '/src/Responder/AlbumResponder.php',
            $this->readStdout()
        );
    }

    public function testDefinitionNamesItselfMakeResponder(): void
    {
        $this->assertSame('make:responder', (new ResponderCommand())->define()->getName());
    }

    public function testForceOverwritesAnExistingResponder(): void
    {
        $this->runCommand(['Album']);
        file_put_contents($this->root . '/src/Responder/AlbumResponder.php', 'stale');

        $status = $this->runCommand(['Album', '--force']);

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString(
            'stale',
            (string) file_get_contents($this->root . '/src/Responder/AlbumResponder.php')
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
        $this->runCommand(['Album']);

        $status = $this->runCommand(['Album']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('already exists', $this->readStderr());
    }

    public function testTheContractIsAliasedSoTheNameCanNeverCollide(): void
    {
        // `make:responder Responder` is the pathological case: the suffix is
        // already there, so the class is named Responder - and without the alias
        // the stub would emit `implements Responder` beside
        // `use ...\Responder;`, which does not compile.
        $status = $this->runCommand(['Responder']);

        $contents = (string) file_get_contents($this->root . '/src/Responder/Responder.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'final class Responder implements ResponderContract',
            $contents
        );
    }

    public function testTheResponderDirectoryIsCreatedWhenItIsAbsent(): void
    {
        // A project that has never had a responder has no src/Responder, and the
        // default path is only a default - nothing guarantees it exists.
        $this->safeDeleteDirectory($this->root . '/src/Responder');

        $status = $this->runCommand(['Album']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Responder/AlbumResponder.php');
    }

    public function testTheSuffixIsNotDoubledWhenTheUserSuppliesIt(): void
    {
        $status = $this->runCommand(['AlbumResponder']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Responder/AlbumResponder.php');
        $this->assertFileDoesNotExist($this->root . '/src/Responder/AlbumResponderResponder.php');
    }

    public function testTheWholeResponderIsRendered(): void
    {
        // Asserted whole rather than by substring: this is generated code nobody
        // reviews, so a dropped use statement or a mangled signature has to fail
        // here or it ships.
        $status = $this->runCommand(['Album']);

        $expected = "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\Responder;\n"
            . "\n"
            . "use Phalcon\Contracts\ADR\Payload\Payload;\n"
            . "use Phalcon\Contracts\ADR\Responder\Responder as ResponderContract;\n"
            . "use Phalcon\Http\RequestInterface;\n"
            . "use Phalcon\Http\ResponseInterface;\n"
            . "\n"
            . "final class AlbumResponder implements ResponderContract\n"
            . "{\n"
            . "    public function __invoke(\n"
            . "        RequestInterface \$request,\n"
            . "        ResponseInterface \$response,\n"
            . "        Payload \$payload\n"
            . "    ): ResponseInterface {\n"
            . "        \$response->setJsonContent(\$payload->getResult());\n"
            . "\n"
            . "        return \$response;\n"
            . "    }\n"
            . "}\n";

        $this->assertSame(0, $status);
        $this->assertSame(
            $expected,
            (string) file_get_contents($this->root . '/src/Responder/AlbumResponder.php')
        );
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('make:responder', ResponderCommand::class, $arguments);
    }
}
