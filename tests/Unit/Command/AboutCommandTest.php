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

use Crest\Command\AboutCommand;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Bound;
use Crest\Tests\Support\CapturesOutput;
use PHPUnit\Framework\TestCase;

use const PHP_VERSION;

final class AboutCommandTest extends TestCase
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

    public function testDefinitionNamesItselfAbout(): void
    {
        $this->assertSame('about', (new AboutCommand())->define()->getName());
    }

    public function testReportsPhalconPhpAndCrestRows(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $input  = new Input('about', new Bound([], [], []));

        $status = (new AboutCommand())->handle($input, $output);
        $text   = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('PHP', $text);
        $this->assertStringContainsString(PHP_VERSION, $text);
        $this->assertStringContainsString('Phalcon', $text);
        $this->assertStringContainsString('Crest', $text);
    }
}
