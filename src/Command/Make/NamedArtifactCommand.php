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
use Crest\Generator\Placement;

use function sprintf;

/**
 * Base for a make:* command that writes one class from a user-given name.
 *
 * A subclass sets KEY and SUFFIX, and its definition must declare a `name`
 * argument and a `--force` option: this class reads both. KEY selects the
 * configured path, the namespace and the stub.
 *
 * A subclass that must tell the developer how to wire the class overrides
 * guidance(). A subclass whose stub needs more values overrides replacements().
 */
abstract class NamedArtifactCommand extends ProjectCommand
{
    /** @var string */
    protected const KEY = '';

    /** @var string */
    protected const SUFFIX = '';

    public function handle(Input $input, Output $output): int
    {
        $config    = $this->config($input);
        $placement = $this->placement($config, $input->argumentString('name'), static::KEY, static::SUFFIX);

        $this->writer($config)->render(
            $placement->file,
            static::KEY,
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
     * Prints what the developer must do to make the class run. The default
     * prints nothing.
     */
    protected function guidance(Placement $placement, Output $output): void
    {
    }

    /**
     * Stub values in addition to `namespace` and `class`.
     *
     * @return array<string, string>
     */
    protected function replacements(Placement $placement): array
    {
        return [];
    }
}
