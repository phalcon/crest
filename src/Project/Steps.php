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

namespace Crest\Project;

use Crest\Console\Exceptions\Exception;

use function array_is_list;
use function is_array;
use function is_string;
use function sprintf;

/**
 * The commands that `crest new` runs after it writes the project. The `new`
 * key of crest.php lists them:
 *
 *   'new' => [['composer', 'install'], ['crest', 'up']],
 *
 * Each command is an argv list: the program, then its arguments. There is no
 * shell. No key means no commands. Only `crest new` reads the key.
 */
final class Steps
{
    /**
     * The value of the `new` key, as crest.php states it. Null when there is
     * no key.
     *
     * @return list<non-empty-list<string>>
     */
    public static function fromConfig(mixed $declared): array
    {
        if (null === $declared) {
            return [];
        }

        if (false === is_array($declared) || false === array_is_list($declared)) {
            throw new Exception("'new' in crest.php must be a list of commands, e.g. [['composer', 'install']]");
        }

        $steps = [];

        foreach ($declared as $index => $step) {
            if (false === self::isCommand($step)) {
                throw new Exception(
                    sprintf("'new' entry %d in crest.php must be a list of strings, e.g. ['crest', 'up']", $index + 1)
                );
            }

            $steps[] = $step;
        }

        return $steps;
    }

    /**
     * The `new` key of a crest.php, and no other key. The other keys belong
     * to the crest of the project, which can be newer than this crest.
     *
     * @return list<non-empty-list<string>>
     */
    public static function fromFile(string $file): array
    {
        /** @var array<string, mixed> $declared */
        $declared = require $file;

        return self::fromConfig($declared['new'] ?? null);
    }

    /**
     * A non-empty list of non-empty strings.
     *
     * @phpstan-assert-if-true non-empty-list<non-empty-string> $step
     */
    private static function isCommand(mixed $step): bool
    {
        if (false === is_array($step) || [] === $step || false === array_is_list($step)) {
            return false;
        }

        foreach ($step as $word) {
            if (false === is_string($word) || '' === $word) {
                return false;
            }
        }

        return true;
    }
}
