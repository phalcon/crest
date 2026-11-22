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

use function array_keys;
use function array_map;
use function max;
use function sprintf;
use function str_pad;
use function strlen;
use function var_export;

/**
 * The decisions of the user that crest.php holds: the flavor, the root
 * namespace, the front controller, the paths and the runtime (D07).
 * `crest new` and `crest init` write crest.php from them through the
 * project-config stub. An MVC project also gets the models key (#54).
 */
final class Settings
{
    /**
     * The models key of an MVC project (#53), with the camelCase and the
     * getSet (#70) answers. The comments help the user who reads crest.php.
     */
    private const MODELS = "    'models'    => [\n"
        . "        // Property names in camelCase. A change renames the properties of\n"
        . "        // each model, so choose before the first make:model.\n"
        . "        'camelCase' => %s,\n"
        . "        // Protected properties with a getter and a setter for each, as\n"
        . "        // devtools --get-set. A change changes the base of each model.\n"
        . "        'getSet'    => %s,\n"
        . "        // The class name of a table, when the default name does not fit,\n"
        . "        // e.g. 'user_logins' => 'Login'.\n"
        . "        'classes'   => [],\n"
        . "    ],\n";

    /**
     * @param array<string, false|string> $paths     False for `views`: an MVC project with no
     *                                               views (#83).
     * @param bool|null                   $camelCase The models.camelCase answer of an MVC
     *                                               project. Null gives no models key: ADR and
     *                                               CLI have no models.
     * @param bool|null                   $getSet    The models.getSet answer of an MVC project
     *                                               (#70). Null gives false in the models key.
     * @param string|null                 $engine    The view engine of an MVC project with an
     *                                               engine other than phtml (#85). Null gives no
     *                                               templateEngine key.
     */
    public function __construct(
        public readonly Flavor $flavor,
        public readonly string $namespace,
        public readonly ?string $bootstrap,
        public readonly array $paths,
        public readonly Runtime $runtime,
        public readonly ?bool $camelCase = null,
        public readonly ?bool $getSet = null,
        public readonly ?string $engine = null,
    ) {
    }

    /**
     * The values of the project-config stub. The stub stays plain string
     * replacement, so the parts that change in shape are whole lines here.
     *
     * @return array<string, string>
     */
    public function replacements(): array
    {
        return [
            'bootstrap' => null === $this->bootstrap
                ? ''
                : sprintf("    'bootstrap' => %s::class,\n", $this->bootstrap),
            'flavor'    => $this->flavor->value,
            'models'    => null === $this->camelCase
                ? ''
                : sprintf(
                    self::MODELS,
                    var_export($this->camelCase, true),
                    var_export($this->getSet ?? false, true)
                ),
            'namespace'      => $this->namespace,
            'paths'          => $this->pathLines(),
            'runtime'        => $this->runtime->render(),
            'templateEngine' => null === $this->engine
                ? ''
                : sprintf("    'templateEngine' => %s,\n", var_export($this->engine, true)),
        ];
    }

    /**
     * One line for each path. The keys line up, as in the rest of the file.
     */
    private function pathLines(): string
    {
        $lengths = array_map(static fn (string $key): int => strlen($key), array_keys($this->paths));
        $width   = max([0, ...$lengths]) + 2;
        $lines   = '';

        foreach ($this->paths as $key => $path) {
            $lines .= sprintf("        %s => %s,\n", str_pad("'" . $key . "'", $width), var_export($path, true));
        }

        return $lines;
    }
}
