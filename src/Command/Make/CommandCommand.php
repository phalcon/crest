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

use Crest\Command\ProjectCommand;
use Crest\Commands;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;

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
final class CommandCommand extends ProjectCommand
{
    private const KEY    = 'command';
    private const SUFFIX = 'Command';

    public function define(): Definition
    {
        return Definition::for('make:command', 'Create a crest command')
            ->argument('name', true, 'Command name, e.g. Greet')
            ->option('force', 'Overwrite an existing command');
    }

    public function handle(Input $input, Output $output): int
    {
        $config    = $this->config($input);
        $placement = $this->placement($config, $input->argumentString('name'), self::KEY, self::SUFFIX);
        $name      = $this->registryName($placement->class);

        $writer = $this->writer($config);

        $writer->render(
            $placement->file,
            self::KEY,
            [
                'namespace' => $placement->namespace,
                'class'     => $placement->class,
                'command'   => $name,
            ],
            true === $input->option('force')
        );

        $output->success(sprintf('Created %s', $placement->file));
        $output->line('Nothing lists it yet. Declare it in the package composer.json:');
        $output->line();
        $output->line('    "extra": {');
        $output->line(sprintf('        "%s": {', Commands::KEY));
        $output->line('            "commands": {');
        $output->line(
            sprintf(
                '                "%s": "%s"',
                $name,
                str_replace('\\', '\\\\', $placement->namespace . '\\' . $placement->class)
            )
        );
        $output->line('            }');
        $output->line('        }');
        $output->line('    }');

        return 0;
    }

    /**
     * The name the registry answers to. Falls back to the whole class when
     * stripping the suffix leaves nothing, so `make:command Command` still
     * yields a usable name rather than an empty one.
     */
    private function registryName(string $class): string
    {
        return strtolower(substr($class, 0, -strlen(self::SUFFIX))) ?: strtolower($class);
    }
}
