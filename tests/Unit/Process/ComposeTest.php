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

namespace Crest\Tests\Unit\Process;

use Crest\Process\Compose;
use PHPUnit\Framework\TestCase;

final class ComposeTest extends TestCase
{
    public function testATerminalGetsNoDashT(): void
    {
        $this->assertSame(
            ['exec', 'app', 'composer', 'install'],
            Compose::exec('app', ['composer', 'install'], true)
        );
    }

    public function testEachVariableComesBeforeTheService(): void
    {
        $this->assertSame(
            ['exec', '-e', 'A=1', '-e', 'B=two', 'app', 'x'],
            Compose::exec('app', ['x'], true, ['A' => '1', 'B' => 'two'])
        );
    }

    public function testWithoutATerminalDockerGetsDashT(): void
    {
        // CI or a pipe: docker compose exec must not ask for a TTY.
        $this->assertSame(
            ['exec', '-T', 'web', 'composer', 'install'],
            Compose::exec('web', ['composer', 'install'], false)
        );
    }
}
