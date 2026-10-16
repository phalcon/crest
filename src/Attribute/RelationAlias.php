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

namespace Crest\Attribute;

use Attribute;

/**
 * The alias of one relation of a model base that make:model writes (#51).
 * The key names the foreign key, in the form of the skipped lines of
 * make:model. The developer can change the alias, and crest keeps it when it
 * writes the base again.
 *
 * crest does not create this class: it reads the attribute with the PHP
 * tokenizer (Crest\Generator\RelationAliases). The class is for the IDE, for
 * PHPStan in the project, and for the docs. PHP loads an attribute class only
 * when code calls ReflectionAttribute::newInstance(), so a production install
 * without crest works. The name and the parameters are a contract with the
 * generated bases: do not change them.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RelationAlias
{
    public function __construct(
        public readonly string $key,
        public readonly string $alias,
    ) {
    }
}
