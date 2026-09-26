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

use Composer\InstalledVersions;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Registry;
use Crest\Tests\Support\Console\FakeCommand;
use Crest\Tests\Support\Console\ThrowingCommand;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    private const KEY = 'crest-registry-test-key';

    /**
     * The real composer dataset, kept so tearDown can put it back. The shape
     * mirrors what getAllRawData() returns and reload() demands.
     *
     * @var array{
     *     root: array{
     *         name: string, pretty_version: string, version: string,
     *         reference: string|null, type: string, install_path: string,
     *         aliases: string[], dev: bool
     *     },
     *     versions: array<string, array{dev_requirement: bool, extra?: array<string, mixed>}>
     * }
     */
    private array $installed;

    protected function setUp(): void
    {
        // getAllRawData() is global state; every discovery test swaps in its
        // own dataset and setUp/tearDown put the real one back so the rest of
        // the suite still sees genuine composer metadata.
        $this->installed = InstalledVersions::getAllRawData()[0];
    }

    protected function tearDown(): void
    {
        InstalledVersions::reload($this->installed);
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

    public function testDiscoveryAddsAContributedCommand(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        $registry = (new Registry())->withDiscovery(self::KEY);

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
    }

    public function testDiscoveryIgnoresANonArrayCommandsEntry(): void
    {
        $this->installExtra([self::KEY => ['commands' => 'nonsense']]);

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIgnoresANonArrayExtraEntry(): void
    {
        $this->installExtra([self::KEY => 'nonsense']);

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

    public function testDiscoveryIgnoresAPackageWithNoMatchingExtraKey(): void
    {
        $this->installExtra(['some-other-tool' => ['commands' => ['x' => FakeCommand::class]]]);

        $this->assertSame([], (new Registry())->withDiscovery(self::KEY)->all());
    }

    public function testDiscoveryIsSkippedEntirelyWithoutAKey(): void
    {
        $this->installExtra([self::KEY => ['commands' => ['fake' => FakeCommand::class]]]);

        // No withDiscovery() call, so the contributed command stays invisible.
        $this->assertSame([], (new Registry())->all());
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

    public function testWithDiscoveryIsChainableAndSeededNamesStillResolve(): void
    {
        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->withDiscovery('crest-registry-test-key');

        $this->assertSame(FakeCommand::class, $registry->get('fake'));
    }

    /**
     * Replaces the installed-package set with a single synthetic package
     * carrying the given composer `extra` block.
     *
     * @param array<string, mixed> $extra
     */
    private function installExtra(array $extra): void
    {
        InstalledVersions::reload([
            'root'     => $this->installed['root'],
            'versions' => ['vendor/pkg' => ['dev_requirement' => false, 'extra' => $extra]],
        ]);
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
        InstalledVersions::reload([
            'root'     => $this->installed['root'],
            'versions' => [
                'vendor/bad'  => ['dev_requirement' => false, 'extra' => $first],
                'vendor/good' => ['dev_requirement' => false, 'extra' => $second],
            ],
        ]);
    }
}
