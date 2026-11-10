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

/**
 * The view vendor_names of the second schema, on the vendors of that schema.
 */
final class BillingVendorNamesViewMigration extends BillingMigration
{
    protected string $table = 'vendor_names';

    protected function getStatementsMysql(): array
    {
        return $this->view();
    }

    protected function getStatementsPgsql(): array
    {
        return $this->view();
    }

    /**
     * @return list<string>
     */
    private function view(): array
    {
        return ['CREATE VIEW ' . self::SCHEMA . '.vendor_names AS SELECT name FROM ' . self::SCHEMA . '.vendors'];
    }
}
