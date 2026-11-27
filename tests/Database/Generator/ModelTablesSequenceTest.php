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

namespace Crest\Tests\Database\Generator;

use Crest\Generator\ModelNames;
use Crest\Generator\ModelTables;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\CustomersMigration;
use Crest\Tests\Support\Migrations\InvoiceNumbersSequenceMigration;

use function getenv;

/**
 * A sequence on MariaDB gives no model for --all (#101). The MySQL suite has
 * this group too, and MySQL has no sequences, so there the test skips.
 *
 * @group mysql
 */
final class ModelTablesSequenceTest extends AbstractDatabaseTestCase
{
    public function testASequenceIsNotATable(): void
    {
        if ('mariadb' !== getenv('driver')) {
            $this->markTestSkipped('MySQL has no sequences.');
        }

        $this->skipListTablesOnOldCphalcon();
        $this->createTables(CustomersMigration::class, InvoiceNumbersSequenceMigration::class);

        $this->assertSame(['customers'], ModelTables::of($this->adapter(), [], ModelNames::of([]))->names());
    }
}
