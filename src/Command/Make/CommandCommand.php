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

namespace Crest\Command\Make;

use Crest\Commands;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\Placement;

use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;

/**
 * Generates a crest command, for a package that wants to contribute its own.
 *
 * The registry has exactly one way in - `extra.crest.commands` in a package's
 * composer.json, read from every installed package including the root project.
 * There is no autoload scan and no convention directory, so a generated command
 * is invisible to `crest list` until that block exists. Crest prints it rather
 * than editing the manifest: this would otherwise be the only command that
 * writes to composer.json, and it would be so for three lines of output.
 *
 * The registry name is derived by lowercasing the class, minus its suffix, which
 * is right for the single-word case and an obvious placeholder otherwise -
 * `SendEmails` gives `sendemails`, not `send-emails`. Nothing can derive
 * `migration:run` from a class name, so the generated definition is a starting
 * point either way.
 */
final class CommandCommand extends NamedArtifactCommand
{
    public function define(): Definition
    {
        return Definition::for('make:command', 'Create a crest command')
            ->argument('name', true, 'Command name, e.g. Greet')
            ->option('force', 'Overwrite an existing command');
    }

    protected function guidance(Placement $placement, Output $output): void
    {
        $output->line('Nothing lists it yet. Declare it in the package composer.json:');
        $output->line();
        $output->line('    "extra": {');
        $output->line(sprintf('        "%s": {', Commands::KEY));
        $output->line('            "commands": {');
        $output->line(
            sprintf(
                '                "%s": "%s"',
                $this->registryName($placement->class),
                str_replace('\\', '\\\\', $placement->namespace . '\\' . $placement->class)
            )
        );
        $output->line('            }');
        $output->line('        }');
        $output->line('    }');
    }

    protected function key(): string
    {
        return 'command';
    }

    protected function replacements(Placement $placement): array
    {
        return ['command' => $this->registryName($placement->class)];
    }

    protected function suffix(): string
    {
        return 'Command';
    }

    /**
     * The name the registry answers to. Falls back to the whole class when
     * stripping the suffix leaves nothing, so `make:command Command` still
     * yields a usable name rather than an empty one.
     */
    private function registryName(string $class): string
    {
        return strtolower(substr($class, 0, -strlen($this->suffix()))) ?: strtolower($class);
    }
}
