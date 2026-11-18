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

/**
 * The array shapes of the Generator namespace, in one place. A class imports
 * each shape that it uses with @phpstan-import-type.
 *
 * @phpstan-type Draft array{relation: Relation, reason: string|null}
 * @phpstan-type Names array<string, array{name: string, line: int}>
 * @phpstan-type Relation array{
 *     kind: string,
 *     fields: list<string>,
 *     class: string,
 *     referenced: list<string>,
 *     alias: string,
 *     key: string
 * }
 * @phpstan-type Stored array<string, array{alias: string, line: int}>
 * @phpstan-type Token array{int, string, int}|string
 */
interface GeneratorTypes
{
}
