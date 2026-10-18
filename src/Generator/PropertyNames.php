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

use Crest\Attribute\PropertyName;

use function sprintf;
use function var_export;

/**
 * The PropertyName attributes of a model base (#53). Each attribute names a
 * column and its property:
 *
 *     #[\Crest\Attribute\PropertyName('first name', name: 'firstName')]
 *
 * crest writes one for each name of --property. The developer can change the
 * name, and crest keeps it (BaseAttributes).
 */
final class PropertyNames
{
    private const NOTE = " *\n"
        . " * You can change a property name in a PropertyName attribute: crest keeps\n"
        . " * it when it writes this file again. Then change the code that uses the\n"
        . " * property.\n";

    /**
     * One attribute line for each name, in the order of the columns: the
     * column, quoted with var_export(), and the name as a named argument.
     * ModelNames has checked that the name is a usable property name. ''
     * for no names.
     *
     * @param array<array-key, string> $names The column and its property
     */
    public static function attributes(array $names): string
    {
        $text = '';

        foreach ($names as $column => $name) {
            // PHP makes a numeric column an integer key.
            $text .= sprintf(
                "#[\\%s(%s, name: '%s')]\n",
                PropertyName::class,
                var_export((string) $column, true),
                $name
            );
        }

        return $text;
    }

    /**
     * The paragraph for the docblock of the base about the attributes. ''
     * for no names.
     *
     * @param array<string, string> $names
     */
    public static function note(array $names): string
    {
        return [] === $names ? '' : self::NOTE;
    }
}
