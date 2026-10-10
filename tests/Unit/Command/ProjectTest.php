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

namespace Crest\Tests\Unit\Command;

use Crest\Tests\Support\Console\ContextCommand;
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;

use function unlink;

use const PHP_EOL;

final class ProjectTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('project');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testAPackageCommandSeesTheProject(): void
    {
        $status = $this->runProjectCommand('context', ContextCommand::class, []);

        $this->assertSame(0, $status);
        $this->assertSame(
            $this->root . PHP_EOL . $this->root . '/src/Action' . PHP_EOL . 'App\Action' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testWithoutCrestPhpThePackageCommandGetsTheInitHint(): void
    {
        unlink($this->root . '/crest.php');

        $status = $this->runProjectCommand('context', ContextCommand::class, []);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("no crest.php found; run 'crest init'", $this->readStderr());
    }
}
