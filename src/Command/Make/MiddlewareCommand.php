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

/**
 * Generates an ADR Middleware - a wrapper around the handler chain that may
 * pass the request through, decorate the response, short-circuit with its own,
 * or throw into the error responder.
 *
 * The generated class is inert until the router's middleware map names it, and
 * crest will not edit the project's bootstrap to do that. So the command prints
 * the registration instead (the stub fragment-guidance-middleware holds the
 * text): the file is crest's to write, the wiring is the developer's to place.
 */
final class MiddlewareCommand extends NamedArtifactCommand
{
    protected function description(): string
    {
        return 'Create an ADR middleware';
    }

    protected function example(): string
    {
        return 'Auth';
    }

    protected function key(): string
    {
        return 'middleware';
    }

    protected function suffix(): string
    {
        return 'Middleware';
    }
}
