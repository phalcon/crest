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

use Crest\Console\Exceptions\Exception;

use function explode;
use function implode;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtoupper;
use function substr;
use function trim;

/**
 * Turns a route into somewhere to write a file.
 *
 * The route-to-class rule is the framework's and is reached through
 * ActionResolver; this class only splits placeholders off the path and turns
 * the resulting class name into a relative file path.
 */
final class Convention
{
    private readonly string $baseNamespace;

    private readonly ActionResolver $resolver;

    public function __construct(string $baseNamespace, ActionResolver $resolver)
    {
        // Router::setBaseNamespace() rtrims backslashes; relativePath below
        // subtracts strlen($this->baseNamespace), so both ends must agree or
        // the path is off by one character.
        $this->baseNamespace = rtrim($baseNamespace, '\\');
        $this->resolver      = $resolver;
    }

    /**
     * The class crest generates for a route.
     */
    public function target(string $method, string $path): Target
    {
        $split = $this->split($path);
        $fqcn  = $this->resolver->classFor($this->baseNamespace, $method, $split['path']);

        $position  = strrpos($fqcn, '\\');
        $namespace = false === $position ? '' : substr($fqcn, 0, $position);
        $class     = false === $position ? $fqcn : substr($fqcn, $position + 1);

        $relative = str_replace(
            '\\',
            '/',
            trim(substr($fqcn, strlen($this->baseNamespace)), '\\')
        ) . '.php';

        return new Target(
            $fqcn,
            $namespace,
            $class,
            $relative,
            $split['attributes'],
            strtoupper($method),
            $path
        );
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        $path = trim($path, '/');

        return '' === $path ? [] : explode('/', $path);
    }

    /**
     * One pass over the path, producing both halves at once: the static
     * segments that identify the class, and every placeholder name that becomes
     * a request attribute.
     *
     * Placeholders must come last. The convention encodes which segments exist
     * in the class name, not where a value sits among them, so a static segment
     * after a placeholder cannot be expressed - `/album/{id}/edit` has no class
     * name that describes it. Rejecting it here is the difference between
     * telling the user and silently writing a file that answers a different URL
     * from the one they asked for.
     *
     * @return array{path: string, attributes: list<string>}
     */
    private function split(string $path): array
    {
        $attributes = [];
        $static     = [];

        foreach ($this->segments($path) as $segment) {
            if (true === str_starts_with($segment, '{')) {
                $attributes[] = trim($segment, '{}');

                continue;
            }

            if ([] !== $attributes) {
                throw new Exception(
                    sprintf(
                        "'%s' cannot follow a placeholder; arguments come last, "
                        . "so write the route as '%s'",
                        $segment,
                        $this->suggest($path)
                    )
                );
            }

            $static[] = $segment;
        }

        return [
            'path'       => '/' . implode('/', $static),
            'attributes' => $attributes,
        ];
    }

    /**
     * The same route with the placeholders moved to the end, which is how the
     * convention spells it.
     */
    private function suggest(string $path): string
    {
        $static     = [];
        $attributes = [];

        foreach ($this->segments($path) as $segment) {
            if (true === str_starts_with($segment, '{')) {
                $attributes[] = $segment;

                continue;
            }

            $static[] = $segment;
        }

        return '/' . implode('/', [...$static, ...$attributes]);
    }
}
