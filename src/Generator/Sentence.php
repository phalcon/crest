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

namespace Crest\Generator;

use function array_pop;
use function implode;

/**
 * Names joined as in a sentence: "a", "a and b", "a, b and c". The messages
 * of make:model use it (#52, #53).
 */
final class Sentence
{
    /**
     * @param list<string> $names
     */
    public static function join(array $names): string
    {
        $last = array_pop($names);

        if (null === $last) {
            return '';
        }

        return [] === $names ? $last : implode(', ', $names) . ' and ' . $last;
    }
}
