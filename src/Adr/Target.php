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

namespace Crest\Adr;

/**
 * A route resolved to the class that will answer it.
 */
final class Target
{
    /**
     * @param list<string> $attributes Placeholder names, in path order.
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $namespace,
        public readonly string $class,
        public readonly string $relativePath,
        public readonly array $attributes,
        public readonly string $method,
        public readonly string $path,
    ) {
    }
}
