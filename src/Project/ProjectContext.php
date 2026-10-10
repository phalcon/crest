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
 * The project that a command runs against, as a package that adds commands
 * sees it. Read-only, and only what does not depend on the flavor.
 *
 * @api
 */
interface ProjectContext
{
    /**
     * The namespace of a named location, for example `action`.
     */
    public function namespaceFor(string $key): string;

    /**
     * The absolute path of a named location, for example `action`.
     */
    public function path(string $key): string;

    /**
     * The project root: the folder of crest.php.
     */
    public function root(): string;
}
