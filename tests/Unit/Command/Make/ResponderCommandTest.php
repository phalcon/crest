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
use Crest\Tests\Support\NamedArtifactCommandTestCase;

use function file_get_contents;

final class ResponderCommandTest extends NamedArtifactCommandTestCase
{
    protected const COMMAND     = ResponderCommand::class;

    protected const DECLARATION = 'final class Responder implements ResponderContract';

    protected const DIRECTORY   = 'src/Responder';

    protected const NAME        = 'make:responder';

    protected const SUFFIX      = 'Responder';

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
}
