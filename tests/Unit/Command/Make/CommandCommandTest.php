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

use Crest\Command\Make\CommandCommand;
use Crest\Commands;
use Crest\Tests\Support\NamedArtifactCommandTestCase;

use function file_get_contents;
use function file_put_contents;
use function mkdir;

use const PHP_EOL;

final class CommandCommandTest extends NamedArtifactCommandTestCase
{
    public function testACopyInTheAdrFolderOfTheProjectStillWins(): void
    {
        // A project that published command.stub before it moved to the shared
        // folder keeps its copy: the project flavor folder is the first level.
        mkdir($this->root . '/resources/stubs/adr', 0o775, true);
        file_put_contents($this->root . '/resources/stubs/adr/command.stub', "<?php\n// old copy {{ class }}\n");

        $status = $this->runCommand(['Greet']);

        $this->assertSame(0, $status);
        $this->assertSame(
            "<?php\n// old copy GreetCommand\n",
            (string) file_get_contents($this->root . '/src/Command/GreetCommand.php')
        );
    }

    public function testAnMvcProjectGetsTheCommandAndItsGuidance(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $status = $this->runCommand(['Greet']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'namespace App\Command;',
            (string) file_get_contents($this->root . '/src/Command/GreetCommand.php')
        );
        $this->assertStringContainsString('Nothing lists it yet.', $this->readStdout());
    }

    public function testTheExtraBlockIsPrintedWithEscapedBackslashes(): void
    {
        // The registry has no other way in, so the block is the deliverable.
        // composer.json is JSON: the namespace separators have to arrive
        // doubled or the declaration does not parse.
        $this->runCommand(['Greet']);

        $expected = 'Created ' . $this->root . '/src/Command/GreetCommand.php' . PHP_EOL
            . 'Nothing lists it yet. Declare it in the package composer.json:' . PHP_EOL
            . PHP_EOL
            . '    "extra": {' . PHP_EOL
            . '        "' . Commands::KEY . '": {' . PHP_EOL
            . '            "commands": {' . PHP_EOL
            . '                "greet": "App\\\\Command\\\\GreetCommand"' . PHP_EOL
            . '            }' . PHP_EOL
            . '        }' . PHP_EOL
            . '    }' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testTheRegistryNameFallsBackToTheWholeClass(): void
    {
        // The registry name falls back to the whole class rather than the empty
        // string stripping the suffix would leave.
        $status = $this->runCommand(['Command']);

        $contents = (string) file_get_contents($this->root . '/src/Command/Command.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString("Definition::for('command',", $contents);
    }

    public function testTheSuffixDoesNotLeakIntoTheRegistryName(): void
    {
        // The registry name is derived from the class minus its suffix, so
        // spelling the suffix out must not leak into it as 'greetcommand'.
        $status = $this->runCommand(['GreetCommand']);

        $contents = (string) file_get_contents($this->root . '/src/Command/GreetCommand.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString("Definition::for('greet',", $contents);
    }

    public function testTheWholeCommandIsRendered(): void
    {
        // Asserted whole rather than by substring: this is generated code nobody
        // reviews, so a dropped use statement or a mangled signature has to fail
        // here or it ships.
        $status = $this->runCommand(['Greet']);

        $expected = "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\Command;\n"
            . "\n"
            . "use Crest\Console\Command\Command as CrestCommand;\n"
            . "use Crest\Console\Input;\n"
            . "use Crest\Console\Output;\n"
            . "use Crest\Console\Parsing\Definition;\n"
            . "\n"
            . "final class GreetCommand extends CrestCommand\n"
            . "{\n"
            . "    public function define(): Definition\n"
            . "    {\n"
            . "        return Definition::for('greet', 'Describe what this command does');\n"
            . "    }\n"
            . "\n"
            . "    public function handle(Input \$input, Output \$output): int\n"
            . "    {\n"
            . "        \$output->success('greet ran');\n"
            . "\n"
            . "        return 0;\n"
            . "    }\n"
            . "}\n";

        $this->assertSame(0, $status);
        $this->assertSame(
            $expected,
            (string) file_get_contents($this->root . '/src/Command/GreetCommand.php')
        );
    }

    protected function command(): string
    {
        return CommandCommand::class;
    }

    protected function commandName(): string
    {
        return 'make:command';
    }

    protected function declaration(): string
    {
        return 'final class Command extends CrestCommand';
    }

    protected function directory(): string
    {
        return 'src/Command';
    }

    protected function suffix(): string
    {
        return 'Command';
    }
}
