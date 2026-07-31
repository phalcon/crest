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

use function sprintf;

/**
 * Generates an ADR Middleware - a wrapper around the handler chain that may
 * pass the request through, decorate the response, short-circuit with its own,
 * or throw into the error responder.
 *
 * The generated class is inert until the router's middleware map names it, and
 * crest will not edit the project's bootstrap to do that. So the command prints
 * the registration instead: the file is crest's to write, the wiring is the
 * developer's to place.
 */
final class MiddlewareCommand extends ProjectCommand
{
    private const KEY    = 'middleware';
    private const SUFFIX = 'Middleware';

    public function define(): Definition
    {
        return Definition::for('make:middleware', 'Create an ADR middleware')
            ->argument('name', true, 'Middleware name, e.g. Auth')
            ->option('force', 'Overwrite an existing middleware');
    }

    public function handle(Input $input, Output $output): int
    {
        $config    = $this->config($input);
        $placement = $this->placement($config, $input->argumentString('name'), self::KEY, self::SUFFIX);

        $writer = $this->writer($config);

        $writer->render(
            $placement->file,
            self::KEY,
            [
                'namespace' => $placement->namespace,
                'class'     => $placement->class,
            ],
            true === $input->option('force')
        );

        $output->success(sprintf('Created %s', $placement->file));
        $output->line('Nothing runs it yet. Add it to the router\'s middleware map:');
        $output->line();
        $output->line(
            sprintf(
                "    \$router->setMiddlewareMap(['' => [\\%s\\%s::class]]);",
                $placement->namespace,
                $placement->class
            )
        );
        $output->line();
        $output->line(
            "The key is a namespace suffix under the base namespace: '' guards every "
            . "action, '\\Album' only the actions beneath it."
        );

        return 0;
    }
}
