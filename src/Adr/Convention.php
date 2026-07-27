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
 * The route-to-class rules are the framework's and are reached through
 * CandidateSource; this class only splits placeholders off the path and turns
 * the winning class name into a relative file path.
 */
final class Convention
{
    private readonly string $baseNamespace;

    private readonly CandidateSource $source;

    public function __construct(string $baseNamespace, CandidateSource $source)
    {
        // Router::setBaseNamespace() rtrims backslashes; relativePath below
        // subtracts strlen($this->baseNamespace), so both ends must agree or
        // the path is off by one character.
        $this->baseNamespace = rtrim($baseNamespace, '\\');
        $this->source        = $source;
    }

    /**
     * Every class the router would try for this path, in try order. Used to
     * warn when a generated action changes how an existing route resolves.
     *
     * @return list<string>
     */
    public function candidates(string $method, string $path): array
    {
        return $this->source->candidatesFor(
            $this->baseNamespace,
            $method,
            $this->split($path)['path']
        );
    }

    /**
     * The class crest generates for a route: the first candidate the router
     * would try for the path's static prefix.
     */
    public function target(string $method, string $path): Target
    {
        $split      = $this->split($path);
        $candidates = $this->source->candidatesFor($this->baseNamespace, $method, $split['path']);

        if ([] === $candidates) {
            throw new Exception(
                sprintf("no action class could be derived for '%s %s'", $method, $path)
            );
        }

        $fqcn      = $candidates[0];
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
     * One pass over the path, producing both halves at once: the static prefix
     * that identifies the class, and every placeholder name that becomes a
     * request attribute.
     *
     * The prefix stops at the first placeholder because the framework camelizes
     * segments blindly - camelize('{id}') is '{id}' - so a placeholder passed
     * through would end up inside a class name.
     *
     * @return array{path: string, attributes: list<string>}
     */
    private function split(string $path): array
    {
        $attributes = [];
        $static     = [];
        $stopped    = false;

        foreach ($this->segments($path) as $segment) {
            if (true === str_starts_with($segment, '{')) {
                $stopped      = true;
                $attributes[] = trim($segment, '{}');

                continue;
            }

            if (false === $stopped) {
                $static[] = $segment;
            }
        }

        return [
            'path'       => '/' . implode('/', $static),
            'attributes' => $attributes,
        ];
    }
}
