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

namespace Crest\Tests\Support\Migrations;

use Phalcon\Talon\Database\Schema\AbstractSchema;

/**
 * The view customer_names, on the table customers. Create customers first.
 * AbstractDatabaseTestCase removes the view.
 */
final class CustomerNamesViewMigration extends AbstractSchema
{
    protected string $table = 'customer_names';

    protected function getStatementsMysql(): array
    {
        return ['CREATE VIEW customer_names AS SELECT id, name FROM customers'];
    }

    protected function getStatementsPgsql(): array
    {
        return ['CREATE VIEW customer_names AS SELECT id, name FROM customers'];
    }

    protected function getStatementsSqlite(): array
    {
        return ['CREATE VIEW customer_names AS SELECT id, name FROM customers'];
    }
}
