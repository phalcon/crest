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

namespace Crest\Tests\Unit\Command\Config;

use Crest\Command\Config\ShowCommand;
use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function preg_replace;
use function unlink;

use const PHP_EOL;

final class ShowCommandTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('config-show', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->writeCrestPhp();
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testADefaultPathIsNotReportedAsDeclared(): void
    {
        // Declaring `views` leaves `action` on its default. Marking the whole
        // block declared would say the project asked for something it did not.
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['views' => 'templates']];\n"
        );

        $this->runCommand();

        // Normalized: the column width now follows the longest default key, so
        // asserting the padding here would pin something this test is not about.
        $output = $this->normalized();

        $this->assertStringContainsString('action ' . $this->root . '/src/Action default', $output);
        $this->assertStringContainsString('views ' . $this->root . '/templates declared', $output);
    }

    public function testAnEmptyCrestPhpShowsTheDefaults(): void
    {
        $status = $this->runCommand();

        $output = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Source: ' . $this->root . '/crest.php', $output);
        $this->assertStringContainsString('adr', $output);
        $this->assertStringContainsString('App', $output);
        $this->assertStringContainsString($this->root, $output);
    }

    public function testDeclaredPathsAreListed(): void
    {
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['views' => 'templates']];\n"
        );

        $this->runCommand();

        $output = $this->readStdout();

        // The declared key and the surviving default both appear, resolved to
        // absolute locations.
        $this->assertStringContainsString($this->root . '/templates', $output);
        $this->assertStringContainsString($this->root . '/src/Action', $output);
    }

    public function testDeclaredValuesAreDistinguishedFromDefaults(): void
    {
        // namespace is declared; flavor and the paths are not, so they keep
        // their defaults and must read as default.
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['namespace' => 'Shop'];\n"
        );

        $this->runCommand();

        $output = $this->readStdout();

        $this->assertStringContainsString('declared', $output);
        $this->assertStringContainsString('default', $output);
    }

    public function testDefinitionNamesItselfConfigShow(): void
    {
        $this->assertSame('config:show', (new ShowCommand())->define()->getName());
    }

    public function testEveryValueIsMarkedDefaultWhenCrestPhpStatesNothing(): void
    {
        $this->runCommand();

        $output = $this->readStdout();

        $this->assertStringContainsString('default', $output);
        $this->assertStringNotContainsString('declared', $output);
    }

    public function testTheConfigFileIsNamedWhenOneWasUsed(): void
    {
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn [];\n");

        $this->runCommand();

        $this->assertStringContainsString($this->root . '/crest.php', $this->readStdout());
    }

    public function testTheWholeReportIsRenderedForADeclaredProject(): void
    {
        // Paths are declared out of alphabetical order, so the listing only
        // reads correctly because it is sorted rather than merged-and-printed.
        //
        // The flavor is mvc, which has no default paths, so only the two
        // declared keys appear - `action` belongs to ADR alone.
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['flavor' => 'mvc', 'namespace' => 'Shop', "
            . "'paths' => ['views' => 'templates', 'admin' => 'backend']];\n"
        );

        $this->runCommand();

        $expected = 'Source: ' . $this->root . '/crest.php' . PHP_EOL
            . PHP_EOL
            . 'ITEM VALUE ORIGIN' . PHP_EOL
            . 'root ' . $this->root . ' crest.php' . PHP_EOL
            . 'flavor mvc declared' . PHP_EOL
            . 'namespace Shop declared' . PHP_EOL
            . PHP_EOL
            . 'PATH LOCATION ORIGIN' . PHP_EOL
            . 'admin ' . $this->root . '/backend declared' . PHP_EOL
            . 'views ' . $this->root . '/templates declared' . PHP_EOL;

        $this->assertSame($expected, $this->normalized());
    }

    public function testTheWholeReportIsRenderedForAnEmptyCrestPhp(): void
    {
        $this->runCommand();

        $expected = 'Source: ' . $this->root . '/crest.php' . PHP_EOL
            . PHP_EOL
            . 'ITEM VALUE ORIGIN' . PHP_EOL
            . 'root ' . $this->root . ' crest.php' . PHP_EOL
            . 'flavor adr default' . PHP_EOL
            . 'namespace App default' . PHP_EOL
            . PHP_EOL
            . 'PATH LOCATION ORIGIN' . PHP_EOL
            . 'action ' . $this->root . '/src/Action default' . PHP_EOL
            . 'command ' . $this->root . '/src/Command default' . PHP_EOL
            . 'middleware ' . $this->root . '/src/Middleware default' . PHP_EOL
            . 'provider ' . $this->root . '/src/Provider default' . PHP_EOL
            . 'responder ' . $this->root . '/src/Responder default' . PHP_EOL;

        $this->assertSame($expected, $this->normalized());
    }

    public function testWithoutCrestPhpItStopsWithTheInitHint(): void
    {
        unlink($this->root . '/crest.php');

        $this->assertSame(1, $this->runCommand());
        $this->assertSame("crest: no crest.php found; run 'crest init'" . PHP_EOL, $this->readStderr());
    }

    /**
     * The report is column-aligned, and the scratch directory name varies per
     * run, so the padding does too. Collapsing runs of spaces lets the content
     * be asserted exactly without asserting the width.
     */
    private function normalized(): string
    {
        return (string) preg_replace('/ {2,}/', ' ', $this->readStdout());
    }

    private function runCommand(): int
    {
        $registry = (new Registry())->add('config:show', ShowCommand::class);
        $kernel   = new Kernel(
            Commands::NAME,
            $registry,
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', 'config:show', '--directory', $this->root]);
    }
}
