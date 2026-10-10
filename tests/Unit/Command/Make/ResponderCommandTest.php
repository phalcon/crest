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
use Crest\Generator\Stub;
use Crest\Tests\Support\NamedArtifactCommandTestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;

use const PHP_EOL;

final class ResponderCommandTest extends NamedArtifactCommandTestCase
{
    public function testAProjectCanAddGuidance(): void
    {
        // The packaged responder has no guidance fragment. A project that
        // publishes one gets it printed after "Created".
        $path = Stub::overridePath($this->root, 'adr', Stub::FRAGMENT_PREFIX . 'guidance-responder');

        mkdir(dirname($path), 0o775, true);
        file_put_contents($path, "Wire {{ class }} in {{ namespace }}.\n");

        $this->runCommand(['Album']);

        $this->assertStringEndsWith('Wire AlbumResponder in App\Responder.' . PHP_EOL, $this->readStdout());
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

    protected function command(): string
    {
        return ResponderCommand::class;
    }

    protected function commandName(): string
    {
        return 'make:responder';
    }

    protected function declaration(): string
    {
        return 'final class Responder implements ResponderContract';
    }

    protected function directory(): string
    {
        return 'src/Responder';
    }

    protected function suffix(): string
    {
        return 'Responder';
    }
}
