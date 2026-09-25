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
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\Placement;

use function sprintf;
use function ucfirst;

/**
 * Base for a make:* command that writes one class from a user-given name.
 *
 * A subclass gives key(), suffix(), description() and example(). This class
 * declares the `name` argument and the `--force` option, because it reads
 * both.
 *
 * A subclass that must tell the developer how to wire the class overrides
 * guidance(). A subclass whose stub needs more values overrides replacements().
 */
abstract class NamedArtifactCommand extends ProjectCommand
{
    public function define(): Definition
    {
        $key = $this->key();

        return Definition::for('make:' . $key, $this->description())
            ->argument('name', true, sprintf('%s name, e.g. %s', ucfirst($key), $this->example()))
            // No declared default: resolveOptions() supplies false for a flag
            // without consulting one, so passing it would state something that
            // is never read.
            ->option('force', sprintf('Overwrite an existing %s', $key));
    }

    public function handle(Input $input, Output $output): int
    {
        $key       = $this->key();
        $config    = $this->config($input);
        $placement = $this->placement($config, $input->argumentString('name'), $key, $this->suffix());

        $this->writer($config)->render(
            $placement->file,
            $key,
            [
                'namespace' => $placement->namespace,
                'class'     => $placement->class,
            ] + $this->replacements($placement),
            true === $input->option('force')
        );

        $output->success(sprintf('Created %s', $placement->file));
        $this->guidance($placement, $output);

        return 0;
    }

    /**
     * The command description, for example `Create an ADR middleware`.
     */
    abstract protected function description(): string;

    /**
     * An example name for the help text, for example `Auth`.
     */
    abstract protected function example(): string;

    /**
     * Prints what the developer must do to make the class run. The default
     * prints nothing.
     */
    protected function guidance(Placement $placement, Output $output): void
    {
    }

    /**
     * The configuration key. It selects the configured path, the namespace and
     * the stub.
     */
    abstract protected function key(): string;

    /**
     * Stub values in addition to `namespace` and `class`.
     *
     * @return array<string, string>
     */
    protected function replacements(Placement $placement): array
    {
        return [];
    }

    /**
     * The suffix of the class name, for example `Middleware`.
     */
    abstract protected function suffix(): string;
}
