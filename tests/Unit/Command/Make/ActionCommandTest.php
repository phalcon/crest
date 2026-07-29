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

use Crest\Command\Make\ActionCommand;
use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\ScratchDirectory;
use Phalcon\ADR\Router\Router;
use PHPUnit\Framework\TestCase;

use function chdir;
use function file_get_contents;
use function file_put_contents;
use function getcwd;

final class ActionCommandTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    private string $previousCwd = '';

    protected function setUp(): void
    {
        $this->makeScratchDirectory('make-action', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->captureStreams();

        // The command writes real files, and Config::discover() falls back to
        // the working directory when --directory does not reach it. Under
        // mutation testing that fallback is reachable, and from the repository
        // root it would generate into the actual src/ tree. Running from the
        // scratch directory keeps even the fallback contained.
        $this->previousCwd = (string) getcwd();
        chdir($this->root);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);

        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testDefinitionNamesItselfMakeAction(): void
    {
        $this->assertSame('make:action', (new ActionCommand())->define()->getName());
    }

    public function testForceOverwritesAnExistingAction(): void
    {
        $this->runCommand(['GET', '/health']);
        file_put_contents($this->root . '/src/Action/Health/GetHealth.php', 'stale');

        $status = $this->runCommand(['GET', '/health', '--force']);

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString(
            'stale',
            (string) file_get_contents($this->root . '/src/Action/Health/GetHealth.php')
        );
    }

    public function testPlaceholderPathGeneratesTheResourceAction(): void
    {
        $status = $this->runCommand(['GET', '/company/{id}']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Action/Company/GetCompany.php');
    }

    public function testRefusesToOverwriteWithoutForce(): void
    {
        $this->runCommand(['GET', '/health']);

        $status = $this->runCommand(['GET', '/health']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('already exists', $this->readStderr());
    }

    public function testStaticTwoSegmentPathWritesTheOperationAction(): void
    {
        $status = $this->runCommand(['GET', '/company/all']);

        $file = $this->root . '/src/Action/Company/All/GetCompanyAll.php';

        $this->assertSame(0, $status);
        $this->assertFileExists($file);

        $contents = (string) file_get_contents($file);

        $this->assertStringContainsString('namespace App\Action\Company\All;', $contents);
        $this->assertStringContainsString('final class GetCompanyAll implements Action', $contents);
        $this->assertStringContainsString('Responder $responder', $contents);
    }

    public function testViewResponderUsesTheViewStub(): void
    {
        $status = $this->runCommand(['GET', '/privacy', '--responder=view']);

        $contents = (string) file_get_contents($this->root . '/src/Action/Privacy/GetPrivacy.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString('ViewResponder $responder', $contents);
        $this->assertStringContainsString("withTemplate('privacy/index')", $contents);
    }

    public function testWritesTheAttributeAccessorForPlaceholders(): void
    {
        $this->runCommand(['GET', '/company/{id}']);

        $contents = (string) file_get_contents($this->root . '/src/Action/Company/GetCompany.php');

        $this->assertStringContainsString("\$id = \$request->getAttributes()->get('id');", $contents);
    }

    public function testAttributeAccessorsAreSeparatedFromTheBodyByABlankLine(): void
    {
        $this->runCommand(['GET', '/company/users/{id}/{userId}']);

        $contents = (string) file_get_contents(
            $this->root . '/src/Action/Company/Users/GetCompanyUsers.php'
        );

        // Exact block: one accessor per placeholder, then a single blank line
        // before the body the stub already carries.
        $expected = "        \$id = \$request->getAttributes()->get('id');\n"
            . "        \$userId = \$request->getAttributes()->get('userId');\n"
            . "\n"
            . "        \$payload = Payload::success([]);";

        $this->assertStringContainsString($expected, $contents);
    }

    public function testPlaceholdersProduceAParamsDeclaration(): void
    {
        $this->runCommand(['GET', '/album/edit/{id}']);

        $contents = (string) file_get_contents(
            $this->root . '/src/Action/Album/Edit/GetAlbumEdit.php'
        );

        // Asserted whole, through to the closing brace, because the block is
        // built by concatenation - a substring check lets a dropped line or a
        // missing separator through, and this is generated code nobody reviews.
        $expected = "    }\n"
            . "\n"
            . "    /**\n"
            . "     * Trailing route attributes, in path order. Constrains, casts and\n"
            . "     * converts them after the route has matched.\n"
            . "     */\n"
            . "    public static function params(): array\n"
            . "    {\n"
            . "        return [\n"
            . "            'id' => ['type' => 'string'],\n"
            . "        ];\n"
            . "    }\n"
            . "}";

        $this->assertStringContainsString($expected, $contents);
    }

    public function testParamsAreDeclaredInPathOrder(): void
    {
        $this->runCommand(['GET', '/company/users/{id}/{userId}']);

        $contents = (string) file_get_contents(
            $this->root . '/src/Action/Company/Users/GetCompanyUsers.php'
        );

        // Declaration order matches path order, so the accessors and the
        // constraints line up with the segments they describe.
        $this->assertStringContainsString(
            "            'id' => ['type' => 'string'],\n"
            . "            'userId' => ['type' => 'string'],",
            $contents
        );
    }

    public function testActionWithNoPlaceholdersDeclaresNoParams(): void
    {
        $this->runCommand(['GET', '/health']);

        $contents = (string) file_get_contents(
            $this->root . '/src/Action/Health/GetHealth.php'
        );

        $this->assertStringNotContainsString('params()', $contents);
    }

    public function testViewTemplateStripsPlaceholderBraces(): void
    {
        $this->runCommand(['GET', '/company/{id}', '--responder=view']);

        $contents = (string) file_get_contents($this->root . '/src/Action/Company/GetCompany.php');

        // Braces must not survive into the template name: dropping either
        // str_replace would leave 'company/{id/index' or 'company/id}/index'.
        $this->assertStringContainsString("withTemplate('company/id/index')", $contents);
    }

    public function testRootRouteGetsTheIndexTemplate(): void
    {
        $this->runCommand(['GET', '/', '--responder=view']);

        $contents = (string) file_get_contents($this->root . '/src/Action/Get.php');

        $this->assertStringContainsString("withTemplate('index/index')", $contents);
    }

    public function testActionWithNoPlaceholdersHasNoAccessorBlock(): void
    {
        $this->runCommand(['GET', '/health']);

        $contents = (string) file_get_contents($this->root . '/src/Action/Health/GetHealth.php');

        $this->assertStringNotContainsString('getAttributes()', $contents);
        $this->assertStringContainsString(
            "    {\n        \$payload = Payload::success([]);",
            $contents
        );
    }

    public function testPathArgumentIsRequired(): void
    {
        $status = $this->runCommand(['GET']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'path'", $this->readStderr());
    }

    public function testMethodArgumentIsRequired(): void
    {
        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'method'", $this->readStderr());
    }

    public function testUnknownResponderIsRejected(): void
    {
        $status = $this->runCommand(['GET', '/health', '--responder', 'xml']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "unknown responder 'xml'; expected json or view",
            $this->readStderr()
        );
    }

    public function testResponderNameIsCaseInsensitive(): void
    {
        $status = $this->runCommand(['GET', '/privacy', '--responder', 'VIEW']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'ViewResponder $responder',
            (string) file_get_contents($this->root . '/src/Action/Privacy/GetPrivacy.php')
        );
    }

    public function testGeneratedActionIsTheOnlyClassThatAnswersItsRoute(): void
    {
        // One path names exactly one class, so nothing can shadow what is
        // generated. This replaces the old candidate warning, which existed
        // only because the router used to try several class shapes per path.
        $this->runCommand(['GET', '/company/all']);

        $router = new Router();
        $router->setBaseNamespace('App\Action');

        $this->assertSame(
            '/company/all',
            $router->pathFor('App\Action\Company\All\GetCompanyAll')
        );
    }

    public function testCreatedPathAndRouteAreReported(): void
    {
        $this->runCommand(['GET', '/company/all']);

        $output = $this->readStdout();

        $this->assertStringContainsString(
            'Created ' . $this->root . '/src/Action/Company/All/GetCompanyAll.php',
            $output
        );
        $this->assertStringContainsString('Answers GET /company/all', $output);
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        $registry = (new Registry())->add('make:action', ActionCommand::class);
        $kernel   = new Kernel(
            Commands::NAME,
            $registry,
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', 'make:action', ...$arguments, '--directory', $this->root]);
    }
}
