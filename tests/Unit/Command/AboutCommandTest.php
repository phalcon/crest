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
use Crest\Commands;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\PackageVersion;
use Crest\Console\Parsing\Bound;
use Crest\Tests\Support\CapturesOutput;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function phpversion;

use const PHP_EOL;
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
        $status = $this->about();

        // Asserted whole rather than by substring: the row values are built by
        // concatenation, and a loose assertion lets a dropped separator or a
        // reordered operand through unnoticed.
        $expected = 'ITEM     VALUE' . PHP_EOL
            . 'PHP      ' . PHP_VERSION . PHP_EOL
            . 'Phalcon  ' . $this->expectedPhalcon() . PHP_EOL
            . 'Crest    ' . PackageVersion::of(Commands::PACKAGE) . PHP_EOL;

        $this->assertSame(0, $status);
        $this->assertSame($expected, $this->readStdout());
    }

    public function testPhalconRowNamesTheSourceItResolvedFrom(): void
    {
        $this->about();

        $text = $this->readStdout();

        // Whichever variant is installed, the row has to say which one - that
        // is the whole point of the command.
        if (true === extension_loaded('phalcon')) {
            $this->assertStringContainsString(' (ext-phalcon)', $text);

            return;
        }

        $this->assertStringContainsString(' (phalcon/phalcon)', $text);
    }

    public function testCrestRowResolvesToARealVersion(): void
    {
        $this->about();

        $this->assertStringContainsString(
            'Crest    ' . PackageVersion::of(Commands::PACKAGE),
            $this->readStdout()
        );
        $this->assertNotSame(PackageVersion::UNKNOWN, PackageVersion::of(Commands::PACKAGE));
    }

    private function about(): int
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $input  = new Input('about', new Bound([], [], []));

        return (new AboutCommand())->handle($input, $output);
    }

    private function expectedPhalcon(): string
    {
        if (true === extension_loaded('phalcon')) {
            return (string) phpversion('phalcon') . ' (ext-phalcon)';
        }

        return PackageVersion::of('phalcon/phalcon') . ' (phalcon/phalcon)';
    }
}
