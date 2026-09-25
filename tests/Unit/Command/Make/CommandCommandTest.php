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

use const PHP_EOL;

final class CommandCommandTest extends NamedArtifactCommandTestCase
{
    protected const COMMAND     = CommandCommand::class;

    protected const DECLARATION = 'final class Command extends CrestCommand';

    protected const DIRECTORY   = 'src/Command';

    protected const NAME        = 'make:command';

    protected const SUFFIX      = 'Command';

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
}
