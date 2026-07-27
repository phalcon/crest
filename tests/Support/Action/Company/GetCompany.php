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

namespace Crest\Tests\Support\Action\Company;

/**
 * Stands in for an action that already exists on disk.
 *
 * make:action warns when a candidate other than the one it is about to write
 * already resolves, and that check is a plain class_exists(). This class exists
 * only to be found by it: the router lists it as a lower-precedence candidate
 * for `GET /company/all`, so generating that route must report it.
 */
final class GetCompany
{
}
