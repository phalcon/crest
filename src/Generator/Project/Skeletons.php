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

use function array_keys;
use function implode;
use function sprintf;
use function strtolower;

/**
 * The flavors that `new` can create, each with its skeleton. `new` and
 * `stub:publish` (for a project stub) check a flavor here.
 */
final class Skeletons
{
    /**
     * Flavor => the skeleton of its projects. CLI has no project yet. The
     * keys are the values of Flavor; a constant cannot read an enum value on
     * PHP 8.1.
     */
    private const SKELETONS = [
        'adr' => AdrSkeleton::class,
        'mvc' => MvcSkeleton::class,
    ];

    /**
     * @return non-empty-list<string>
     */
    public static function flavors(): array
    {
        return array_keys(self::SKELETONS);
    }

    /**
     * The skeleton of the flavor. The value is made lowercase first, as
     * --phalcon is.
     */
    public static function for(string $flavor): Skeleton
    {
        $flavor = strtolower($flavor);

        if (false === isset(self::SKELETONS[$flavor])) {
            throw new Exception(
                sprintf("unknown flavor '%s'; expected %s", $flavor, implode(' or ', self::flavors()))
            );
        }

        $class = self::SKELETONS[$flavor];

        return new $class();
    }
}
