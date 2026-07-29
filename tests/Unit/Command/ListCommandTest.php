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

namespace Crest\Tests\Unit\Command;

use Composer\InstalledVersions;
use Crest\Command\ListCommand;
use Crest\Commands;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\PackageVersion;
use Crest\Console\Parsing\Bound;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\Console\FakeCommand;
use PHPUnit\Framework\TestCase;

use function strpos;

use const PHP_EOL;

final class ListCommandTest extends TestCase
{
    use CapturesOutput;

    /**
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
        $this->captureStreams();

        $this->installed = InstalledVersions::getAllRawData()[0];
    }

    protected function tearDown(): void
    {
        InstalledVersions::reload($this->installed);

        $this->closeStreams();
    }

    public function testDefinitionNamesItselfList(): void
    {
        $this->assertSame('list', (new ListCommand())->define()->getName());
    }

    public function testBannerPrecedesTheTable(): void
    {
        $this->listCommands();

        $this->assertStringStartsWith(
            Commands::NAME . ' ' . PackageVersion::of(Commands::PACKAGE) . PHP_EOL . PHP_EOL
            . 'COMMAND',
            $this->readStdout()
        );
    }

    public function testEveryRegisteredCommandIsListedWithItsDescription(): void
    {
        $status = $this->listCommands();

        $output = $this->readStdout();

        $this->assertSame(0, $status);

        foreach (Commands::registry()->all() as $name => $class) {
            $this->assertStringContainsString($name, $output);
            $this->assertStringContainsString((new $class())->define()->getDescription(), $output);
        }
    }

    public function testListsItselfToo(): void
    {
        // A command the user can run must appear in the listing, including
        // this one - otherwise `list` hides the very surface it documents.
        $this->listCommands();

        $this->assertStringContainsString('list', $this->readStdout());
    }

    public function testCommandsAreSortedByName(): void
    {
        $this->listCommands();

        $output = $this->readStdout();

        $this->assertLessThan(strpos($output, 'make:action'), strpos($output, 'about'));
    }

    public function testContributedCommandsAreSortedInAmongTheSeededOnes(): void
    {
        // Discovery appends, so a contributed command arrives after every
        // seeded one no matter what it is called. Sorting is what puts it back
        // where a reader expects it - and the seeded names are already
        // alphabetical, so this is the only way to prove the sort happens.
        InstalledVersions::reload([
            'root'     => $this->installed['root'],
            'versions' => [
                'vendor/pkg' => [
                    'dev_requirement' => false,
                    'extra'           => [
                        Commands::KEY => ['commands' => ['aaa:first' => FakeCommand::class]],
                    ],
                ],
            ],
        ]);

        $this->listCommands();

        $output = $this->readStdout();

        $this->assertLessThan(strpos($output, 'about'), strpos($output, 'aaa:first'));
    }

    private function listCommands(): int
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $input  = new Input('list', new Bound([], [], []));

        return (new ListCommand())->handle($input, $output);
    }
}
