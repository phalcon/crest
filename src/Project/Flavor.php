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

/**
 * Which application architecture a project uses. Flavor is a generator
 * concern only - the console core never sees it.
 */
enum Flavor: string
{
    // Names are the acronyms they stand for; the backed values stay lowercase
    // because they are what a project writes in crest.php and what names the
    // stub directory under resources/stubs.
    case ADR = 'adr';
    case CLI = 'cli';
    case MVC = 'mvc';
}
