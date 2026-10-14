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

use Crest\Project\Flavor;

/**
 * Generates a service provider for the flavor's container.
 *
 * Under ADR that means Phalcon\Container and the Provider contract, whose
 * provide() takes a service Collection. Under MVC it means Phalcon\Di and
 * ServiceProviderInterface, whose register() takes the DI container. Each
 * flavor has its own contract and its own registration call, so each flavor
 * has its own stub and its own guidance.
 *
 * Like make:middleware, the generated class is inert until something calls it,
 * and crest does not edit the project's front controller. It prints the call
 * instead (the stub fragment-guidance-provider holds the text). For ADR, the
 * call includes the parent:: line - omitting that one is a silent failure that
 * takes the ADR services down with it.
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

    protected function flavors(): array
    {
        // A stub for each flavor: ADR registers with Phalcon\Container, MVC with DI.
        return [Flavor::ADR, Flavor::MVC];
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
