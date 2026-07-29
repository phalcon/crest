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

namespace Crest\Tests\Unit\Command\Route;

use Crest\Command\Route\ListCommand;
use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function strpos;

use const PHP_EOL;

final class ListCommandTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('route-list', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testDefinitionNamesItselfRouteList(): void
    {
        $this->assertSame('route:list', (new ListCommand())->define()->getName());
    }

    public function testListsAStaticRoute(): void
    {
        $this->writeAction('Health', 'GetHealth', 'App\Action\Health');

        $status = $this->runCommand();

        $output = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('GET', $output);
        $this->assertStringContainsString('/health', $output);
        $this->assertStringContainsString('App\Action\Health\GetHealth', $output);
    }

    public function testShowsTrailingAttributesFromParams(): void
    {
        // The placeholder only appears if the Action was loaded and its
        // params() read - a name-only scan would print '/album/edit'.
        $this->writeAction(
            'Album/Edit',
            'GetAlbumEdit',
            'App\Action\Album\Edit',
            "    public static function params(): array\n"
            . "    {\n"
            . "        return ['id' => ['type' => 'string']];\n"
            . "    }\n"
        );

        $this->runCommand();

        $this->assertStringContainsString('/album/edit/{id}', $this->readStdout());
    }

    public function testRoutesAreSortedByPath(): void
    {
        // A parent and its child: the shallower path sorts first, but the
        // directory walk descends into the subdirectory whenever the filesystem
        // hands it back first, so this only passes because the listing sorts.
        $this->writeAction('Store/Items', 'GetStoreItems', 'App\Action\Store\Items');
        $this->writeAction('Store', 'GetStore', 'App\Action\Store');
        $this->writeAction('Zebra', 'GetZebra', 'App\Action\Zebra');
        $this->writeAction('Alpha', 'GetAlpha', 'App\Action\Alpha');

        $this->runCommand();

        $expected = 'METHOD  PATH          ACTION' . PHP_EOL
            . 'GET     /alpha        App\Action\Alpha\GetAlpha' . PHP_EOL
            . 'GET     /store        App\Action\Store\GetStore' . PHP_EOL
            . 'GET     /store/items  App\Action\Store\Items\GetStoreItems' . PHP_EOL
            . 'GET     /zebra        App\Action\Zebra\GetZebra' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testTheWholeTableIsRendered(): void
    {
        // Asserted whole: the header row, the column order, the verb and the
        // path are each built separately, and a substring check lets any one of
        // them be wrong while the others carry the assertion.
        $this->writeAction('Session', 'PostSession', 'App\Action\Session');
        $this->writeAction('Health', 'GetHealth', 'App\Action\Health');

        $this->runCommand();

        $expected = 'METHOD  PATH      ACTION' . PHP_EOL
            . 'GET     /health   App\Action\Health\GetHealth' . PHP_EOL
            . 'POST    /session  App\Action\Session\PostSession' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testReportsWhenThereAreNoActions(): void
    {
        $status = $this->runCommand();

        $this->assertSame(0, $status);
        $this->assertSame(
            'no actions found in ' . $this->root . '/src/Action' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testNonPhpFilesAreIgnored(): void
    {
        $this->writeAction('Health', 'GetHealth', 'App\Action\Health');
        file_put_contents($this->root . '/src/Action/Health/notes.md', 'not an action');
        file_put_contents($this->root . '/src/Action/Health/.gitkeep', '');

        $this->runCommand();

        $output = $this->readStdout();

        $this->assertStringContainsString('/health', $output);
        $this->assertStringNotContainsString('notes', $output);
    }

    public function testAClassTheConventionWouldNotProduceIsSkipped(): void
    {
        // pathFor() returns null for a name the convention could not have
        // generated, so a stray helper in the action tree is not a route.
        $this->writeAction('Health', 'GetHealth', 'App\Action\Health');
        $this->writeAction('Health', 'SomeHelper', 'App\Action\Health');

        $this->runCommand();

        $output = $this->readStdout();

        $this->assertStringContainsString('GetHealth', $output);
        $this->assertStringNotContainsString('SomeHelper', $output);
    }

    public function testARootLevelActionIsListed(): void
    {
        // No namespace segments at all, so the verb is the entire class name -
        // the edge of the derivation.
        file_put_contents(
            $this->root . '/src/Action/Get.php',
            "<?php\n\nnamespace App\Action;\n\nfinal class Get\n{\n}\n"
        );

        $this->runCommand();

        $this->assertStringContainsString('GET     /', $this->readStdout());
    }

    private function writeAction(
        string $directory,
        string $class,
        string $namespace,
        string $body = ''
    ): void {
        $target = $this->root . '/src/Action/' . $directory;

        if (false === is_dir($target)) {
            mkdir($target, 0o775, true);
        }

        file_put_contents(
            $this->root . '/src/Action/' . $directory . '/' . $class . '.php',
            "<?php\n\nnamespace " . $namespace . ";\n\nfinal class " . $class . "\n{\n" . $body . "}\n"
        );
    }

    private function runCommand(): int
    {
        $registry = (new Registry())->add('route:list', ListCommand::class);
        $kernel   = new Kernel(
            Commands::NAME,
            $registry,
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', 'route:list', '--directory', $this->root]);
    }
}
