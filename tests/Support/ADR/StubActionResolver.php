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

namespace Crest\Tests\Support\ADR;

use Crest\ADR\ActionResolver;

/**
 * Returns a canned class name and records what it was asked. Deliberately not
 * a second implementation of the routing convention - it computes nothing.
 */
final class StubActionResolver implements ActionResolver
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    public array $calls = [];

    public function __construct(
        private readonly string $class,
        private readonly ?string $path = null,
        private readonly ?string $method = null,
    ) {
    }

    public function classFor(string $baseNamespace, string $method, string $path): string
    {
        $this->calls[] = [$baseNamespace, $method, $path];

        return $this->class;
    }

    public function methodFor(string $baseNamespace, string $class): ?string
    {
        return $this->method;
    }

    public function pathFor(string $baseNamespace, string $class): ?string
    {
        return $this->path;
    }
}
