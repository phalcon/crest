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
use Crest\Generator\Stub;

use function sprintf;
use function ucfirst;

/**
 * Base for a make:* command that writes one class from a user-given name.
 *
 * A subclass gives key(), suffix(), description() and example(). This class
 * declares the `name` argument and the `--force` option, because it reads
 * both. A subclass whose stub needs more values overrides replacements().
 *
 * What the developer must do to make the class run is in the stub
 * `fragment-guidance-<key>`: this class prints it after "Created" when the
 * project or the package has it.
 *
 * @internal
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
        $flavor    = $config->flavor()->value;
        $placement = $this->placement($config, $input->argumentString('name'), $key, $this->suffix());
        $values    = [
            'namespace' => $placement->namespace,
            'class'     => $placement->class,
        ] + $this->replacements($placement);

        $this->writer($config)->render($placement->file, $key, $values, true === $input->option('force'));

        $output->success(sprintf('Created %s', $placement->file));

        // A generator with nothing to say has no fragment. A project can add
        // one, or change the packaged one.
        $stub     = $this->stub($config);
        $guidance = Stub::FRAGMENT_PREFIX . 'guidance-' . $key;

        if (true === $stub->has($flavor, $guidance)) {
            $output->write($stub->render($flavor, $guidance, $values));
        }

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
