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
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;

final class ActionCommandTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('make-action', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
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

        $file = $this->root . '/src/Action/Company/GetCompanyAll.php';

        $this->assertSame(0, $status);
        $this->assertFileExists($file);

        $contents = (string) file_get_contents($file);

        $this->assertStringContainsString('namespace App\Action\Company;', $contents);
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
