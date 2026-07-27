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

namespace Crest\Command;

use Crest\Commands;
use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\PackageVersion;
use Crest\Console\Parsing\Definition;

use function extension_loaded;
use function phpversion;

use const PHP_VERSION;

/**
 * Environment report. Deliberately boots nothing: it must work in a directory
 * with no project, a broken autoloader or no Phalcon at all, which is exactly
 * when someone runs it.
 */
final class AboutCommand extends Command
{
    /**
     * Named for what it is, because Commands::PACKAGE is also in scope here and
     * means a different package.
     */
    private const PHALCON_PACKAGE = 'phalcon/phalcon';

    public function define(): Definition
    {
        return Definition::for('about', 'Show environment and version information');
    }

    public function handle(Input $input, Output $output): int
    {
        $output->table(
            ['ITEM', 'VALUE'],
            [
                ['PHP', PHP_VERSION],
                ['Phalcon', $this->phalcon()],
                ['Crest', $this->crest()],
            ]
        );

        return 0;
    }

    private function crest(): string
    {
        return PackageVersion::of(Commands::PACKAGE);
    }

    /**
     * The C extension and the PHP package are mutually exclusive at runtime;
     * report whichever is actually present.
     */
    private function phalcon(): string
    {
        if (true === extension_loaded('phalcon')) {
            return (string) phpversion('phalcon') . ' (ext-phalcon)';
        }

        if (true === PackageVersion::isInstalled(self::PHALCON_PACKAGE)) {
            return PackageVersion::of(self::PHALCON_PACKAGE) . ' (' . self::PHALCON_PACKAGE . ')';
        }

        return 'not installed';
    }
}
