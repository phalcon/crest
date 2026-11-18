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

namespace Crest\Command\Make;

use Crest\Generator\ModelRelations;

/**
 * The array shapes of the make commands, in one place. A class imports each
 * shape that it uses with @phpstan-import-type.
 *
 * @phpstan-type Parts array{
 *     accessors: list<string>,
 *     attributes: array<string, string>,
 *     base: string,
 *     baseFile: string,
 *     baseNamespace: string,
 *     class: string,
 *     command: string,
 *     dropped: list<string>,
 *     file: string,
 *     getSet: bool,
 *     map: array<string, string>,
 *     names: array<string, string>,
 *     namespace: string,
 *     note: string|null,
 *     properties: array<string, string>,
 *     relations: ModelRelations,
 *     renamed: array<string, array{string, string}>|null,
 *     run: list<string>,
 *     schema: string|null,
 *     table: string
 * }
 */
interface MakeTypes
{
}
