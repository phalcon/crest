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

namespace Crest\ADR;

/**
 * Where an Action class name comes from. One method, so a flavor that routes
 * differently supplies its own implementation without touching the generator.
 *
 * Was CandidateSource: the router used to offer several candidate classes per
 * path and let the first that existed win. One path now names exactly one
 * class, so there is nothing to choose between and nothing to shadow.
 */
interface ActionResolver
{
    /**
     * The Action class the given method and path name, derived from the
     * convention alone. The class need not exist - a generator is about to
     * create it.
     */
    public function classFor(string $baseNamespace, string $method, string $path): string;

    /**
     * The path the given Action class answers, or null when the class is not
     * one this convention would have produced.
     *
     * Unlike classFor(), the class must be loadable: trailing attributes come
     * from its params() declaration, and an unloaded class reports none - so
     * `/album/edit/{id}` would be printed as `/album/edit`.
     */
    public function pathFor(string $baseNamespace, string $class): ?string;
}
