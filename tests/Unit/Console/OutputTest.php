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

namespace Crest\Tests\Unit\Console;

use Crest\Console\Output;
use Crest\Tests\Support\CapturesOutput;
use PHPUnit\Framework\TestCase;

use const PHP_EOL;

final class OutputTest extends TestCase
{
    use CapturesOutput;

    protected function setUp(): void
    {
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
    }

    public function testErrorGoesToStderrNotStdout(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->error('it broke');

        $this->assertSame('', $this->readStdout());
        $this->assertSame('it broke' . PHP_EOL, $this->readStderr());
    }

    public function testLineWritesToStdoutWithNewline(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->line('hello');

        $this->assertSame('hello' . PHP_EOL, $this->readStdout());
    }

    public function testSuccessIsUndecoratedWhenDecorationIsOff(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->success('done');

        $this->assertSame('done' . PHP_EOL, $this->readStdout());
    }

    public function testSuccessWrapsInGreenWhenDecorated(): void
    {
        $output = new Output($this->stdout, $this->stderr, true);

        $output->success('done');

        $this->assertSame("\033[32mdone\033[0m" . PHP_EOL, $this->readStdout());
    }

    public function testTablePadsColumnsToTheWidestCell(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->table(['NAME', 'VALUE'], [['php', '8.4.1'], ['label', 'dev']]);

        $expected = 'NAME   VALUE' . PHP_EOL
            . 'php    8.4.1' . PHP_EOL
            . 'label  dev' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }
}
