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

namespace Crest\Tests\Support;

use Composer\Autoload\ClassLoader;

use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;
use function var_export;

/**
 * A vendor folder as composer leaves it, for the command discovery of the
 * Registry: `composer/installed.json` with the `extra` block of each package,
 * `composer/installed.php` with the root path, and the root `composer.json`.
 * A class loader for the folder is registered, as the composer autoloader
 * does, so the discovery reads the folder. RegistryComposerTest shows that a
 * real `composer install` writes the same files.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait InstalledPackages
{
    private ?ClassLoader $installedLoader = null;

    /**
     * Writes the vendor folder of $directory and registers its class loader.
     * A second call writes the files again and keeps the loader.
     *
     * @param list<mixed> $packages The `extra` block of each package, in
     *                              the order of installed.json.
     * @param mixed       $root     The `extra` block of the root composer.json;
     *                              null writes no composer.json.
     */
    private function installPackages(string $directory, array $packages, mixed $root = null): void
    {
        $vendor = $directory . '/vendor';

        if (false === is_dir($vendor . '/composer')) {
            mkdir($vendor . '/composer', 0o775, true);
        }

        $list = [];
        foreach ($packages as $index => $extra) {
            $list[] = ['name' => 'vendor/package-' . $index, 'version' => '1.0.0', 'extra' => $extra];
        }

        file_put_contents(
            $vendor . '/composer/installed.json',
            (string) json_encode(['packages' => $list, 'dev' => true, 'dev-package-names' => []])
        );
        file_put_contents(
            $vendor . '/composer/installed.php',
            "<?php return " . var_export(['root' => ['install_path' => $directory], 'versions' => []], true) . ";\n"
        );

        if (null !== $root) {
            file_put_contents($directory . '/composer.json', (string) json_encode(['extra' => $root]));
        }

        if (null === $this->installedLoader) {
            $this->installedLoader = new ClassLoader($vendor);
            $this->installedLoader->register();
        }
    }

    /**
     * Unregisters the class loader, so the next test reads only the vendor
     * folder of crest.
     */
    private function uninstallPackages(): void
    {
        $this->installedLoader?->unregister();
        $this->installedLoader = null;
    }
}
