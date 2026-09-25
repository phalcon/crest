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

use Crest\Console\Output;
use Crest\Generator\Placement;

use function sprintf;

/**
 * Generates a service provider for the flavor's container.
 *
 * Under ADR that means Phalcon\Container and the Provider contract, whose
 * provide() takes a service Collection. MVC will register against DI, which has
 * its own contract and its own registration call - which is why this generator
 * is flavor-scoped rather than shared.
 *
 * Like make:middleware, the generated class is inert until something calls it,
 * and crest does not edit the project's front controller. It prints the call
 * instead, including the parent:: line - omitting that one is a silent failure
 * that takes the ADR services down with it.
 */
final class ProviderCommand extends NamedArtifactCommand
{
    protected function description(): string
    {
        return 'Create a service provider';
    }

    protected function example(): string
    {
        return 'Cache';
    }

    protected function guidance(Placement $placement, Output $output): void
    {
        $output->line('Nothing registers it yet. Call it from your front controller:');
        $output->line();
        $output->line('    protected function registerProviders(Container $container): void');
        $output->line('    {');
        $output->line('        parent::registerProviders($container);');
        $output->line();
        $output->line(
            sprintf(
                '        (new \\%s\\%s())->provide($container);',
                $placement->namespace,
                $placement->class
            )
        );
        $output->line('    }');
        $output->line();
        $output->line('Keep the parent call: it is what registers the ADR services.');
    }

    protected function key(): string
    {
        return 'provider';
    }

    protected function suffix(): string
    {
        return 'Provider';
    }
}
