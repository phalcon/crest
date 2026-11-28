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

namespace Crest\Tests\Support\Project;

use Crest\Tests\Support\Migrations\BillingMigration;

/**
 * A SchemaSqlite with a second schema, MIRROR, that has the same tables: the
 * tables of the file are in both schemas (#86). So on SQLite a table name is
 * in two schemas of a list.
 */
final class TwoSchemaSqlite extends SchemaSqlite
{
    public const MIRROR = 'crest_mirror';

    protected const SCHEMAS = [BillingMigration::SCHEMA, self::MIRROR];
}
