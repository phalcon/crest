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
use Crest\Tests\Support\NamedArtifactCommandTestCase;

use function file_get_contents;

use const PHP_EOL;

final class MiddlewareCommandTest extends NamedArtifactCommandTestCase
{
    protected const COMMAND     = MiddlewareCommand::class;

    protected const DECLARATION = 'final class Middleware implements MiddlewareContract';

    protected const DIRECTORY   = 'src/Middleware';

    protected const NAME        = 'make:middleware';

    protected const SUFFIX      = 'Middleware';

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
}
