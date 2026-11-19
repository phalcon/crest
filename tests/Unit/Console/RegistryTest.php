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

namespace Crest\Tests\Unit\Console;

use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Registry;
use Crest\Tests\Support\Console\FakeCommand;
use Crest\Tests\Support\Console\ThrowingCommand;
use Crest\Tests\Support\InstalledPackages;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;
use stdClass;

use function file_put_contents;
use function json_encode;
use function unlink;

final class RegistryTest extends TestCase
{
    use InstalledPackages;
    use ScratchDirectory;

    private const KEY = 'crest-registry-test-key';

    protected function setUp(): void
    {
        // A discovery test writes a vendor folder in the scratch folder and
        // registers its class loader; tearDown unregisters it, so the rest of
        // the suite reads only the vendor folder of crest.
        $this->makeScratchDirectory('registry');
    }

    protected function tearDown(): void
    {
        $this->uninstallPackages();
        $this->removeScratchDirectory();
    }

    public function testAliasesDoNotAppearInAll(): void
    {
        $registry = (new Registry())->add('fake', FakeCommand::class, 'faux', 'f');

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
    }

    public function testAliasesResolveToTheSameClass(): void
    {
        $registry = (new Registry())->add('fake', FakeCommand::class, 'faux', 'f');

        $this->assertSame(FakeCommand::class, $registry->get('fake'));
        $this->assertSame(FakeCommand::class, $registry->get('faux'));
        $this->assertSame(FakeCommand::class, $registry->get('f'));
    }

    public function testConstructorSeedsFromAMap(): void
    {
        $registry = new Registry(['fake' => FakeCommand::class]);

        $this->assertTrue($registry->has('fake'));
    }

    public function testContributedCommandIsVisibleToHasWithoutListingFirst(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        $this->assertTrue((new Registry())->withDiscovery(self::KEY)->has('fake'));
    }

    public function testContributedCommandResolvesThroughGetWithoutListingFirst(): void
    {
        // get() misses the seeded map, so resolve() itself has to trigger the
        // scan - nothing has called all() to do it beforehand.
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        $registry = (new Registry())->withDiscovery(self::KEY);

        $this->assertSame(FakeCommand::class, $registry->get('fake'));
    }

    public function testDefaultNameIsNullWithoutWithDefault(): void
    {
        $this->assertNull((new Registry())->add('fake', FakeCommand::class)->defaultName());
    }

    public function testDescriptionsKeepAllCommandsWithoutATest(): void
    {
        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->add('boom', ThrowingCommand::class);

        $this->assertSame(
            ['boom' => 'A command that always fails', 'fake' => 'A command that exists only for tests'],
            $registry->descriptions()
        );
    }

    public function testDescriptionsKeepOnlyTheCommandsThatPassTheTest(): void
    {
        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->add('boom', ThrowingCommand::class);

        $this->assertSame(
            ['fake' => 'A command that exists only for tests'],
            $registry->descriptions(static fn (Command $command): bool => $command instanceof FakeCommand)
        );
    }

    public function testDiscoveryAddsACommandOfTheRootProject(): void
    {
        // The root project has no entry in installed.json: its own
        // composer.json gives its `extra` block.
        $this->installPackages($this->root, [], [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryAddsAContributedCommand(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        $registry = (new Registry())->withDiscovery(self::KEY);

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
    }

    public function testDiscoveryIgnoresAClassThatIsNotACommand(): void
    {
        $this->installExtra([
            self::KEY => ['commands' => ['plain' => stdClass::class, 'fake' => FakeCommand::class]],
        ]);

        $registry = (new Registry())->withDiscovery(self::KEY);

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
        $this->assertSame(['fake' => 'A command that exists only for tests'], $registry->descriptions());
    }

    public function testDiscoveryIgnoresANonArrayCommandsEntry(): void
    {
        $this->installExtra([self::KEY => ['commands' => 'nonsense']]);

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresANonArrayExtraBlock(): void
    {
        $this->installPackages(
            $this->root,
            ['nonsense', [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]]
        );

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresANonArrayExtraEntry(): void
    {
        $this->installExtra([self::KEY => 'nonsense']);

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresANonArrayPackage(): void
    {
        $this->installExtra([]);
        $this->writeVendorFile(
            'installed.json',
            (string) json_encode([
                'packages' => ['nonsense', ['extra' => [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]]],
            ])
        );

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresANonArrayPackageList(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);
        $this->writeVendorFile('installed.json', '{"packages": "nonsense"}');

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresANonArrayRootExtra(): void
    {
        $this->installPackages($this->root, [], 'nonsense');

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresANumericCommandName(): void
    {
        // A JSON array rather than an object yields int keys; those are not
        // command names and must not be registered.
        $this->installExtra([self::KEY => ['commands' => [FakeCommand::class]]]);

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresAnUnloadableClass(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['ghost' => 'No\\Such\\CommandClass']]]);

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresAPackageWithNoExtraBlock(): void
    {
        $this->installExtra([]);
        $this->writeVendorFile(
            'installed.json',
            (string) json_encode([
                'packages' => [
                    ['name' => 'vendor/plain'],
                    ['extra' => [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]],
                ],
            ])
        );

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresAPackageWithNoMatchingExtraKey(): void
    {
        $this->installExtra(['some-other-tool' => ['commands' => ['x' => FakeCommand::class]]]);

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresARootWithNoInstallPath(): void
    {
        $this->installPackages($this->root, [], [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);
        $this->writeVendorFile('installed.php', "<?php return ['root' => []];\n");

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIsSkippedEntirelyWithoutAKey(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        // No withDiscovery() call, so the contributed command stays invisible.
        $this->assertSame([], (new Registry())->all());
    }

    public function testDiscoveryKeepsASeededAliasForAContributedName(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['f' => ThrowingCommand::class]]]);

        $registry = (new Registry())->add('fake', FakeCommand::class, 'f')->withDiscovery(self::KEY);

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
        $this->assertSame(FakeCommand::class, $registry->get('f'));
    }

    public function testDiscoveryKeepsASeededCommandForAContributedName(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => ThrowingCommand::class]]]);

        $registry = (new Registry())->add('fake', FakeCommand::class)->withDiscovery(self::KEY);

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
    }

    public function testDiscoveryLetsTheRootProjectReplaceAPackageCommand(): void
    {
        $this->installPackages(
            $this->root,
            [[self::KEY => ['commands' => ['same' => FakeCommand::class]]]],
            [self::KEY => ['commands' => ['same' => ThrowingCommand::class]]]
        );

        $this->assertSame(['same' => ThrowingCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryReadsThePackagesBeforeTheRootProject(): void
    {
        $this->installPackages(
            $this->root,
            [[self::KEY => ['commands' => ['fake' => FakeCommand::class]]]],
            [self::KEY => ['commands' => ['boom' => ThrowingCommand::class]]]
        );

        $this->assertSame(
            ['fake' => FakeCommand::class, 'boom' => ThrowingCommand::class],
            (new Registry())->withDiscovery(self::KEY)->all()
        );
    }

    public function testDiscoveryReadsThePackagesWithNoInstalledPhp(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);
        unlink($this->root . '/vendor/composer/installed.php');

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryReadsTheRootProjectWithNoInstalledJson(): void
    {
        $this->installPackages($this->root, [], [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);
        unlink($this->root . '/vendor/composer/installed.json');

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryRunsOnlyOnce(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        $registry = (new Registry())->withDiscovery(self::KEY);

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());

        // A second contributor appearing after the first scan is not picked up:
        // discovery is a one-shot, which is what keeps repeated lookups cheap.
        $this->installExtra([self::KEY => ['commands' => ['boom' => ThrowingCommand::class]]]);

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
    }

    public function testGetGivesNoProviderForANameOutsideEveryPrefix(): void
    {
        // Anchored: the message with a provider starts the same way.
        $registry = (new Registry())->withProviders(['demo:' => 'vendor/demo']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/^unknown command 'other'$/");

        $registry->get('other');
    }

    public function testGetGivesNoProviderWhenACommandHasThePrefix(): void
    {
        // A command with the prefix exists, so the package is installed and
        // only the name is wrong.
        $registry = (new Registry())
            ->add('demo:run', FakeCommand::class)
            ->withProviders(['demo:' => 'vendor/demo']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/^unknown command 'demo:rnu'$/");

        $registry->get('demo:rnu');
    }

    public function testGetGivesNoProviderWhenADiscoveredCommandHasThePrefix(): void
    {
        // The same rule for a command that a package contributes: the miss
        // runs discovery before the message is made.
        $this->installExtra([self::KEY => ['commands' => ['demo:run' => FakeCommand::class]]]);

        $registry = (new Registry())
            ->withDiscovery(self::KEY)
            ->withProviders(['demo:' => 'vendor/demo']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/^unknown command 'demo:rnu'$/");

        $registry->get('demo:rnu');
    }

    public function testGetNamesTheProviderOfAnUnknownCommand(): void
    {
        // The first prefix does not match, so the search must go past it.
        $registry = (new Registry())->withProviders([
            'other:' => 'vendor/other',
            'demo:'  => 'vendor/demo',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown command 'demo:run'; provided by vendor/demo");

        $registry->get('demo:run');
    }

    public function testGetStillThrowsAfterDiscoveryFindsNothing(): void
    {
        // A miss triggers the deferred scan; nothing in the test environment
        // contributes under this key, so the miss must still surface.
        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->withDiscovery('crest-registry-test-key');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown command 'nope'");

        $registry->get('nope');
    }

    public function testGetThrowsForAnUnknownName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown command 'nope'");

        (new Registry())->get('nope');
    }

    public function testHasIsTrueForAnAlias(): void
    {
        $registry = (new Registry())->add('fake', FakeCommand::class, 'fk');

        $this->assertTrue($registry->has('fk'));
        $this->assertFalse($registry->has('nope'));
    }

    public function testScanContinuesPastANumericCommandName(): void
    {
        // The bad entry comes first, so a break would hide the good one.
        $this->installExtra([
            self::KEY => ['commands' => [FakeCommand::class, 'fake' => FakeCommand::class]],
        ]);

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testScanContinuesPastAnUnloadableClass(): void
    {
        $this->installExtra([
            self::KEY => ['commands' => ['ghost' => 'No\\Such\\CommandClass', 'fake' => FakeCommand::class]],
        ]);

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testScanContinuesPastAPackageWithANonArrayCommandsEntry(): void
    {
        $this->installTwoPackages(
            [self::KEY => ['commands' => 'nonsense']],
            [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]
        );

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testScanContinuesPastAPackageWithANonArrayExtraEntry(): void
    {
        // A malformed contributor must be skipped, not abort the whole scan.
        $this->installTwoPackages(
            [self::KEY => 'nonsense'],
            [self::KEY => ['commands' => ['fake' => FakeCommand::class]]]
        );

        $this->assertSame(['fake' => FakeCommand::class], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testSeededNameResolvesWithoutTriggeringDiscovery(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['boom' => ThrowingCommand::class]]]);

        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->withDiscovery(self::KEY);

        $this->assertTrue($registry->has('fake'));

        // The seeded hit answered without scanning, so all() still has to run
        // discovery afterwards and pick the contributed command up.
        $this->assertArrayHasKey('boom', $registry->all());
    }

    public function testStartsEmpty(): void
    {
        $this->assertSame([], (new Registry())->all());
    }

    public function testWithDefaultRecordsTheNameAndIsChainable(): void
    {
        $registry = (new Registry())
            ->withDefault('fake')
            ->add('fake', FakeCommand::class);

        $this->assertSame('fake', $registry->defaultName());
        $this->assertSame(FakeCommand::class, $registry->get('fake'));
    }

    public function testWithDiscoveryIsChainableAndSeededNamesStillResolve(): void
    {
        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->withDiscovery('crest-registry-test-key');

        $this->assertSame(FakeCommand::class, $registry->get('fake'));
    }

    /**
     * One installed package with the given composer `extra` block.
     *
     * @param array<string, mixed> $extra
     */
    private function installExtra(array $extra): void
    {
        $this->installPackages($this->root, [$extra]);
    }

    /**
     * Two packages in scan order, so a guard that aborts the loop instead of
     * skipping one entry is visible.
     *
     * @param array<string, mixed> $first
     * @param array<string, mixed> $second
     */
    private function installTwoPackages(array $first, array $second): void
    {
        $this->installPackages($this->root, [$first, $second]);
    }

    /**
     * Replaces a file of the vendor folder, to give a shape that composer
     * does not write.
     */
    private function writeVendorFile(string $file, string $content): void
    {
        file_put_contents($this->root . '/vendor/composer/' . $file, $content);
    }
}
