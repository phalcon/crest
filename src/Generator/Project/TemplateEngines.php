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

namespace Crest\Generator\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Project\Flavor;

use function count;
use function implode;
use function in_array;
use function sprintf;
use function strtolower;

/**
 * The view engines of a flavor, for `new --template-engine`. The first engine
 * is the default. An empty list is a flavor with no views: then the option is
 * an error. There is no null engine: the empty list is that case.
 *
 * A flavor with more than one engine gets a question. When an engine needs
 * behavior of its own (Volt: a cache folder, a package for v6), the names can
 * become objects in this class, and the callers do not change.
 */
final class TemplateEngines
{
    /**
     * @param list<string> $names The engines of the flavor. The first is the
     *                            default.
     */
    public function __construct(
        private readonly Flavor $flavor,
        private readonly array $names,
    ) {
    }

    /**
     * Asks for the engine when the flavor has more than one and the option is
     * not given. Then select() checks the value.
     */
    public function choose(Output $output, ?string $given): ?string
    {
        if (null === $given && 1 < count($this->names)) {
            $given = $output->choice('Template engine', $this->names, $this->names[0]);
        }

        return $this->select($given);
    }

    public function hasEngines(): bool
    {
        return [] !== $this->names;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }

    /**
     * The engine of the project: the given engine, made lowercase as
     * --phalcon is, else the default. Null when the flavor has no engines
     * and none is given.
     */
    public function select(?string $given): ?string
    {
        if (null === $given) {
            return $this->names[0] ?? null;
        }

        if (false === $this->hasEngines()) {
            throw new Exception(
                sprintf("the '%s' flavor has no views; remove --template-engine", $this->flavor->value)
            );
        }

        $engine = strtolower($given);

        if (false === in_array($engine, $this->names, true)) {
            throw new Exception(
                sprintf("unknown template engine '%s'; expected %s", $engine, implode(' or ', $this->names))
            );
        }

        return $engine;
    }
}
