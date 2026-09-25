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
 * Generates an ADR Responder - the one layer that speaks HTTP, turning a domain
 * payload into a response.
 *
 * The generated class implements the contract directly rather than extending
 * AbstractFormattedResponder: that base composes a formatter chain, which is
 * the right answer for content negotiation and the wrong one to hand someone
 * who asked for a responder to fill in.
 *
 * Boots nothing - it reads config and writes a file, so it keeps working on a
 * project that does not currently run.
 */
final class ResponderCommand extends NamedArtifactCommand
{
    protected function description(): string
    {
        return 'Create an ADR responder';
    }

    protected function example(): string
    {
        return 'Album';
    }

    protected function key(): string
    {
        return 'responder';
    }

    protected function suffix(): string
    {
        return 'Responder';
    }
}
