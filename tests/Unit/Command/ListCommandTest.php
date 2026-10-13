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
use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Console\PackageVersion;
use Crest\Tests\Support\Console\FakeCommand;
use Crest\Tests\Support\RunsACommandDirectly;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function file_put_contents;
use function preg_quote;
use function strpos;

use const PHP_EOL;

final class ListCommandTest extends TestCase
{
    use RunsACommandDirectly;
    use ScratchDirectory;

    /**
     * The commands that have no MVC or CLI version yet.
     */
    private const ADR_ONLY = [
        'container:list',
        'event:list',
        'make:action',
        'make:middleware',
        'make:provider',
        'make:responder',
        'route:list',
    ];

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
        $this->makeScratchDirectory('list', 'src');

        $this->installed = InstalledVersions::getAllRawData()[0];
    }

    protected function tearDown(): void
    {
        InstalledVersions::reload($this->installed);

        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testACliProjectHidesTheAdrOnlyCommands(): void
    {
        $this->writeCrestPhp("['flavor' => 'cli']");

        $this->listCommands();

        $output = $this->readStdout();

        foreach (self::ADR_ONLY as $name) {
            $this->assertDoesNotMatchRegularExpression($this->row($name), $output);
        }

        $this->assertMatchesRegularExpression($this->row('config:show'), $output);
    }

    public function testAMissingConfigFileIsAnError(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($this->root . '/missing.php was not found');

        $this->listCommands(['--config', $this->root . '/missing.php']);
    }

    public function testAnAdrProjectListsEveryCommand(): void
    {
        $this->writeCrestPhp();

        $this->listCommands();

        $output = $this->readStdout();

        foreach (array_keys(Commands::registry()->all()) as $name) {
            $this->assertMatchesRegularExpression($this->row($name), $output);
        }
    }

    public function testAnInvalidCrestPhpIsItsError(): void
    {
        $this->writeCrestPhp("['flavor' => 'x']");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown flavor 'x'");

        $this->listCommands();
    }

    public function testAnMvcProjectHidesTheAdrOnlyCommands(): void
    {
        $this->contribute('aaa:first', FakeCommand::class);
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->listCommands();

        $output = $this->readStdout();

        foreach (self::ADR_ONLY as $name) {
            $this->assertDoesNotMatchRegularExpression($this->row($name), $output);
        }

        // A command that is not a project command, a package command, and the
        // project commands for all flavors.
        foreach (['aaa:first', 'about', 'config:show', 'list', 'make:command', 'new', 'stub:publish'] as $name) {
            $this->assertMatchesRegularExpression($this->row($name), $output);
        }
    }

    public function testBannerPrecedesTheTable(): void
    {
        $this->listCommands();

        $this->assertStringStartsWith(
            Output::MARK . ' ' . Commands::NAME . ' ' . PackageVersion::of(Commands::PACKAGE)
            . PHP_EOL . PHP_EOL
            . 'COMMAND',
            $this->readStdout()
        );
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
        $this->contribute('aaa:first', FakeCommand::class);

        $this->listCommands();

        $output = $this->readStdout();

        $this->assertLessThan(strpos($output, 'about'), strpos($output, 'aaa:first'));
    }

    public function testDefinitionNamesItselfList(): void
    {
        $this->assertSame('list', (new ListCommand())->define()->getName());
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

    public function testTheConfigOptionNamesTheProject(): void
    {
        file_put_contents($this->root . '/mvc.php', "<?php\n\nreturn ['flavor' => 'mvc'];\n");

        $this->listCommands(['--config', $this->root . '/mvc.php']);

        $this->assertDoesNotMatchRegularExpression($this->row('make:action'), $this->readStdout());
    }

    public function testTheWalkUpFromASubfolderFindsTheFlavor(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->handleDirectly(new ListCommand(), ['--directory', $this->root . '/src']);

        $this->assertDoesNotMatchRegularExpression($this->row('make:action'), $this->readStdout());
    }

    /**
     * Makes an installed package contribute one command.
     *
     * @param class-string<Command> $class
     */
    private function contribute(string $name, string $class): void
    {
        InstalledVersions::reload([
            'root'     => $this->installed['root'],
            'versions' => [
                'vendor/pkg' => [
                    'dev_requirement' => false,
                    'extra'           => [Commands::KEY => ['commands' => [$name => $class]]],
                ],
            ],
        ]);
    }

    /**
     * Runs `list` for the scratch folder. Without a crest.php there, the walk
     * up finds none: tests/_output is in /srv, which has no crest.php.
     *
     * @param list<string> $tokens
     */
    private function listCommands(array $tokens = []): int
    {
        return $this->handleDirectly(new ListCommand(), ['--directory', $this->root, ...$tokens]);
    }

    /**
     * The row of a command in the table: its name at the start of a line.
     */
    private function row(string $name): string
    {
        return '/^' . preg_quote($name, '/') . ' /m';
    }
}
