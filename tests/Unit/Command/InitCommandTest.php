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

use Crest\Command\InitCommand;
use Crest\Command\NewCommand;
use Crest\Project\Config;
use Crest\Tests\Support\RunsThroughTheKernel;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chdir;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function mkdir;
use function unlink;

use const PHP_EOL;

/**
 * The scratch root is a composer project with `App\ => src/` and no
 * crest.php. The tests run from it, so a wrong walk cannot write into this
 * repository.
 */
final class InitCommandTest extends TestCase
{
    use RunsThroughTheKernel;
    use ScratchDirectory;

    /**
     * The services of vokuro-adr: app, mysql, mailpit.
     */
    private const VOKURO_COMPOSE = "services:\n"
        . "  app:\n"
        . "    build: .\n"
        . "  mysql:\n"
        . "    image: mysql\n"
        . "  mailpit:\n"
        . "    image: mailpit\n";

    private string $previousCwd = '';

    protected function setUp(): void
    {
        $this->makeScratchDirectory('init', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->captureStreams();

        $this->previousCwd = (string) getcwd();
        chdir($this->root);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);

        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testAComposerJsonWithoutAUsablePsr4EntryStops(): void
    {
        $this->writeComposerJson(['Ghost\\' => 'missing/']);

        $this->assertSame(1, $this->init([]));
        $this->assertSame(
            'crest: no usable psr-4 autoload entry found in composer.json; '
            . "these psr-4 directories do not exist: 'missing'" . PHP_EOL,
            $this->readStderr()
        );
        $this->assertFileDoesNotExist($this->root . '/crest.php');
    }

    public function testADirectoryThatDoesNotExistIsReported(): void
    {
        // A walk up from it would find the composer.json of the scratch root.
        $this->assertSame(1, $this->init(['--directory', $this->root . '/missing']));
        $this->assertSame(
            'crest: ' . $this->root . '/missing is not a directory' . PHP_EOL,
            $this->readStderr()
        );
        $this->assertFileDoesNotExist($this->root . '/crest.php');
    }

    public function testAnExistingCrestPhpIsKept(): void
    {
        $this->writeCrestPhp("['namespace' => 'Kept']");

        $this->assertSame(1, $this->init([]));
        $this->assertSame(
            'crest: ' . $this->root . '/crest.php exists; pass --force to overwrite' . PHP_EOL,
            $this->readStderr()
        );
        $this->assertSame('Kept', Config::discover($this->root)->namespace());
    }

    public function testAParentCrestPhpDoesNotStopInit(): void
    {
        // A crest.php in a parent folder, for example the home folder. init
        // writes into the nearest composer project.
        $this->writeCrestPhp();
        mkdir($this->root . '/inner/src', 0o775, true);
        file_put_contents($this->root . '/inner/composer.json', '{"autoload":{"psr-4":{"Inner\\\\":"src/"}}}');

        $this->assertSame(0, $this->init(['--directory', $this->root . '/inner']));
        $this->assertSame('Inner', Config::discover($this->root . '/inner')->namespace());
    }

    public function testAProjectFromNewGetsTheSameCrestPhp(): void
    {
        $this->runThroughKernel('new', NewCommand::class, ['shop', '--directory', $this->root]);

        $written = (string) file_get_contents($this->root . '/shop/crest.php');

        unlink($this->root . '/shop/crest.php');

        $this->assertSame(0, $this->init(['--directory', $this->root . '/shop']));
        $this->assertSame($written, (string) file_get_contents($this->root . '/shop/crest.php'));
    }

    public function testAProjectThatRequiresCrestGetsNoNextStep(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"App\\\\":"src/"}},"require-dev":{"phalcon/crest":"dev-master"}}'
        );

        $this->init([]);

        $this->assertSame('Created ' . $this->root . '/crest.php' . PHP_EOL, $this->readStdout());
    }

    public function testAVokuroLikeProjectGetsAWorkingCrestPhp(): void
    {
        $this->writeComposerJson(['Vokuro\\' => 'src/']);
        file_put_contents($this->root . '/src/AppFront.php', "<?php\n");
        file_put_contents($this->root . '/docker-compose.yml', self::VOKURO_COMPOSE);

        $status = $this->init([]);
        $config = Config::discover($this->root);

        $this->assertSame(0, $status);
        $this->assertSame('Vokuro', $config->namespace());
        $this->assertSame('Vokuro\\AppFront', $config->bootstrap());
        $this->assertSame('Vokuro\\Action', $config->namespaceFor('action'));
        $this->assertSame($this->root . '/src/Responder', $config->path('responder'));
        $this->assertTrue($config->runtime()->isDocker());
        $this->assertSame('app', $config->runtime()->service);
        $this->assertSame(
            'Created ' . $this->root . '/crest.php' . PHP_EOL
            . 'Next: composer require --dev phalcon/crest' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testDefinitionNamesItselfInit(): void
    {
        $this->assertSame('init', (new InitCommand())->define()->getName());
    }

    public function testForceOverwritesCrestPhp(): void
    {
        $this->writeCrestPhp("['namespace' => 'Old']");

        $this->assertSame(0, $this->init(['--force']));
        $this->assertSame('App', Config::discover($this->root)->namespace());
    }

    public function testInteractiveAnswersAreWritten(): void
    {
        // The questions: namespace, front controller, runtime, and the
        // service for docker.
        $this->answers("Shop\nShop\\Front\ndocker\nweb\n");

        $status = $this->init([], true);
        $config = Config::discover($this->root);

        $this->assertSame(0, $status);
        $this->assertSame('Shop', $config->namespace());
        $this->assertSame('Shop\\Front', $config->bootstrap());
        $this->assertSame('web', $config->runtime()->service);
    }

    public function testNoInteractionWritesTheProposals(): void
    {
        $this->answers("Other\n");

        $this->assertSame(0, $this->init(['-n'], true));
        $this->assertSame('App', Config::discover($this->root)->namespace());
    }

    public function testTheEndOfInputWritesNothing(): void
    {
        // Ctrl+D at the first question.
        $this->assertSame(1, $this->init([], true));
        $this->assertStringEndsWith('crest: no answer; input ended' . PHP_EOL, $this->readStderr());
        $this->assertFileDoesNotExist($this->root . '/crest.php');
    }

    public function testWithoutComposerJsonInitStops(): void
    {
        // From the filesystem root: a walk up from the scratch folder finds
        // the composer.json of this repository.
        $this->assertSame(1, $this->init(['--directory', '/']));
        $this->assertSame(
            'crest: no composer.json found; crest init needs a composer project' . PHP_EOL,
            $this->readStderr()
        );
    }

    /**
     * @param list<string> $tokens
     */
    private function init(array $tokens, bool $interactive = false): int
    {
        return $this->runThroughKernel('init', InitCommand::class, $tokens, $interactive);
    }
}
