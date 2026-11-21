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
use Crest\Project\Config;
use Crest\Project\Flavor;
use Error;
use ReflectionClass;

use function class_exists;

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
 *
 * An ADR front controller that declares registerProviders() already has the
 * override, for example the front of `crest new --database`. A second one is
 * a fatal error, so the guidance prints only the line to add (#81).
 */
final class ProviderCommand extends NamedArtifactCommand
{
    /**
     * The class name of the front controller when it declares
     * registerProviders() itself. Null when the front inherits the method or
     * has none, or when crest cannot load the front.
     */
    private static function overriding(string $front): ?string
    {
        try {
            if (false === class_exists($front)) {
                return null;
            }
        } catch (Error) {
            // The file of the front loads, but its parent class is not there.
            return null;
        }

        $class = new ReflectionClass($front);

        if (false === $class->hasMethod('registerProviders')) {
            return null;
        }

        return $class->getName() === $class->getMethod('registerProviders')->getDeclaringClass()->getName()
            ? $class->getName()
            : null;
    }

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

    protected function guidance(Config $config, Output $output, array $values, string $variant = ''): void
    {
        $bootstrap = $config->bootstrap();
        $front     = Flavor::ADR === $config->flavor() && null !== $bootstrap ? self::overriding($bootstrap) : null;

        if (null === $front) {
            parent::guidance($config, $output, $values, $variant);

            return;
        }

        parent::guidance($config, $output, $values + ['front' => $front], 'override');
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
