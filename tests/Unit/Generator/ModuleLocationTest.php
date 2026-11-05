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

namespace Crest\Tests\Unit\Generator;

use Crest\Console\Exceptions\Exception;
use Crest\Generator\ModuleLocation;
use Crest\Project\Config;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function is_readable;
use function mkdir;
use function setlocale;

use const LC_COLLATE;

/**
 * The structure of a module (spec 3.1), and how crest finds the folder of a
 * module from its key (spec 3.2).
 */
final class ModuleLocationTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('module-location');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->writeCrestPhp("['flavor' => 'mvc']");
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableNames(): iterable
    {
        yield 'a space' => ['my module'];
        yield 'a first digit' => ['9admin'];
        yield 'a slash' => ['admin/users'];
        yield 'a hyphen' => ['admin-panel'];
        yield 'empty' => [''];
        yield 'only an underscore' => ['_'];
    }

    public function testAFileWithTheKeyIsNotAModule(): void
    {
        // Only a folder is a module. A file with the key in its name does
        // not count, so the module gets the path of a new folder, Admin, and
        // not the name of the file, ADMIN.
        mkdir($this->root . '/src/Modules', 0o775, true);
        file_put_contents($this->root . '/src/Modules/ADMIN', 'not a folder');

        $this->assertSame($this->root . '/src/Modules/Admin', ModuleLocation::of($this->config(), 'admin')->path);
    }

    public function testAModulePathOutsidePsr4Stops(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc', 'paths' => ['module' => 'lib/Modules']]");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no psr-4 autoload entry covers 'lib/Modules'");

        ModuleLocation::of($this->config(), 'admin');
    }

    public function testANewNameGivesTheStudlyFolder(): void
    {
        $config = $this->config();
        $panel  = ModuleLocation::of($config, 'admin_panel');

        $this->assertSame('adminpanel', $panel->key);
        $this->assertSame($this->root . '/src/Modules/AdminPanel', $panel->path);
        $this->assertSame('App\\Modules\\AdminPanel', $panel->namespace);

        foreach (['admin', 'Admin'] as $name) {
            $admin = ModuleLocation::of($config, $name);

            $this->assertSame('admin', $admin->key);
            $this->assertSame($this->root . '/src/Modules/Admin', $admin->path);
            $this->assertSame('App\\Modules\\Admin', $admin->namespace);
        }
    }

    public function testAnExistingFolderIsFoundByItsKey(): void
    {
        // The key is not the folder: studly('adminpanel') is Adminpanel. The
        // lookup reads the names of the folders, so it finds AdminPanel.
        mkdir($this->root . '/src/Modules/AdminPanel', 0o775, true);

        $config = $this->config();

        foreach (['adminpanel', 'ADMINPANEL', 'AdminPanel'] as $name) {
            $location = ModuleLocation::of($config, $name);

            $this->assertSame('adminpanel', $location->key);
            $this->assertSame($this->root . '/src/Modules/AdminPanel', $location->path);
            $this->assertSame('App\\Modules\\AdminPanel', $location->namespace);
        }
    }

    public function testAnUnreadableModulePathGivesTheNewFolder(): void
    {
        // No PHP warning: PHPUnit fails a test that causes one.
        mkdir($this->root . '/src/Modules', 0o775, true);
        chmod($this->root . '/src/Modules', 0o000);

        try {
            // root can read a folder with mode 000, so the test proves nothing
            // then.
            if (true === is_readable($this->root . '/src/Modules')) {
                $this->markTestSkipped('this user can read a folder with mode 000');
            }

            $path = ModuleLocation::of($this->config(), 'admin')->path;
        } finally {
            // The scratch folder can be removed only with its mode back.
            chmod($this->root . '/src/Modules', 0o775);
        }

        $this->assertSame($this->root . '/src/Modules/Admin', $path);
    }

    /**
     * @dataProvider unusableNames
     */
    public function testAnUnusableNameStops(string $name): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'" . $name . "' is not a usable module name; expected a single name like 'admin'"
        );

        ModuleLocation::of($this->config(), $name);
    }

    public function testExistingNamesTheModuleAsItWasTyped(): void
    {
        // The hint gives the folder that the error names: make:module
        // admin_panel writes AdminPanel. The key adminpanel would write
        // Adminpanel.
        try {
            ModuleLocation::existing($this->config(), 'admin_panel');
            $this->fail('a module with no Module.php was found');
        } catch (Exception $exception) {
            $this->assertSame(
                "module 'adminpanel' was not found: no Module.php in " . $this->root
                . '/src/Modules/AdminPanel; run crest make:module admin_panel first',
                $exception->getMessage()
            );
        }
    }

    public function testExistingNeedsTheModuleClass(): void
    {
        $config = $this->config();

        try {
            ModuleLocation::existing($config, 'admin');
            $this->fail('a module with no Module.php was found');
        } catch (Exception $exception) {
            $this->assertSame(
                "module 'admin' was not found: no Module.php in " . $this->root
                . "/src/Modules/Admin; run crest make:module admin first",
                $exception->getMessage()
            );
        }

        mkdir($this->root . '/src/Modules/Admin', 0o775, true);
        file_put_contents($this->root . '/src/Modules/Admin/Module.php', '<?php');

        $this->assertSame($this->root . '/src/Modules/Admin', ModuleLocation::existing($config, 'Admin')->path);
    }

    public function testTheNamespacesKeyWins(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc', 'namespaces' => ['module' => 'Acme\\\\Mods']]");

        $this->assertSame('Acme\\Mods\\Admin', ModuleLocation::of($this->config(), 'admin')->namespace);
    }

    public function testThePartsOfAModule(): void
    {
        $location = ModuleLocation::of($this->config(), 'admin');

        $this->assertSame($this->root . '/src/Modules/Admin/Module.php', $location->file());
        $this->assertSame(
            $this->root . '/src/Modules/Admin/Controllers',
            $location->folder(ModuleLocation::CONTROLLERS)
        );
        $this->assertSame($this->root . '/src/Modules/Admin/Forms', $location->folder(ModuleLocation::FORMS));
        $this->assertSame($this->root . '/src/Modules/Admin/Models', $location->folder(ModuleLocation::MODELS));
        $this->assertSame($this->root . '/src/Modules/Admin/views', $location->folder(ModuleLocation::VIEWS));
        $this->assertSame('App\\Modules\\Admin\\Controllers', $location->namespaceOf(ModuleLocation::CONTROLLERS));
        $this->assertSame('App\\Modules\\Admin\\Forms', $location->namespaceOf(ModuleLocation::FORMS));
        $this->assertSame('App\\Modules\\Admin\\Models', $location->namespaceOf(ModuleLocation::MODELS));
        $this->assertSame('Module', ModuleLocation::NAME);
    }

    public function testTwoFoldersWithOneKeyStop(): void
    {
        mkdir($this->root . '/src/Modules/Admin', 0o775, true);
        mkdir($this->root . '/src/Modules/ADMIN', 0o775, true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "two folders give the module 'admin': " . $this->root . '/src/Modules/ADMIN and '
            . $this->root . '/src/Modules/Admin'
        );

        ModuleLocation::of($this->config(), 'admin');
    }

    public function testTwoFoldersWithOneKeyStopInByteOrderInEachLocale(): void
    {
        // scandir() sorts with the collation of the locale: en_US puts Admin
        // before ADMIN. The text must be the same in each locale.
        $previous = setlocale(LC_COLLATE, '0');

        if (false === setlocale(LC_COLLATE, 'en_US.utf8', 'en_US.UTF-8')) {
            $this->markTestSkipped('the en_US locale is not installed');
        }

        mkdir($this->root . '/src/Modules/Admin', 0o775, true);
        mkdir($this->root . '/src/Modules/ADMIN', 0o775, true);

        try {
            ModuleLocation::of($this->config(), 'admin');
            $this->fail('two folders with one key were accepted');
        } catch (Exception $exception) {
            $this->assertSame(
                "two folders give the module 'admin': " . $this->root . '/src/Modules/ADMIN and '
                . $this->root . '/src/Modules/Admin',
                $exception->getMessage()
            );
        } finally {
            setlocale(LC_COLLATE, false === $previous ? 'C' : $previous);
        }
    }

    private function config(): Config
    {
        return Config::discover($this->root);
    }
}
