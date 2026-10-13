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

use Crest\Command\Questions;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Project\Runtime;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

/**
 * The scratch root is a composer project with `App\ => src/` and src/Action,
 * for the questions of init.
 */
final class QuestionsTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->captureStreams();
        $this->makeScratchDirectory('questions', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badFolders(): iterable
    {
        yield 'outside psr-4' => ['lib/Action'];
        yield 'absolute' => ['/srv/src/Action'];
        yield 'with ..' => ['src/../lib/Action'];
        yield 'the root' => ['/'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badNames(): iterable
    {
        yield 'a dash' => ['src/my-actions', 'App\\my-actions'];
        yield 'a digit first' => ['src/1x', 'App\\1x'];
        yield 'a dot segment' => ['src/./Action', 'App\\.\\Action'];
    }

    public function testBootstrapAsksAgainForAnUnusableClass(): void
    {
        $this->answers("not a class\nApp\\Front\n");

        $this->assertSame('App\\Front', Questions::bootstrap($this->interactive(), null));
        $this->assertStringContainsString("'not a class' is not a usable namespace", $this->readStderr());
    }

    public function testBootstrapEmptyWithoutADefaultIsNone(): void
    {
        $this->answers("\n");

        $this->assertNull(Questions::bootstrap($this->interactive(), null));
        $this->assertSame('Front controller class (none for no front controller): ', $this->readStderr());
    }

    public function testBootstrapNoneRefusesTheProposal(): void
    {
        $this->answers("NONE\n");

        $this->assertNull(Questions::bootstrap($this->interactive(), 'App\\AppFront'));
    }

    public function testBootstrapTakesTheDefault(): void
    {
        $this->answers("\n");

        $this->assertSame('App\\AppFront', Questions::bootstrap($this->interactive(), 'App\\AppFront'));
    }

    public function testCheckWithGivesNullForAGoodAnswer(): void
    {
        $check = Questions::checkWith(static fn (string $answer): string => $answer);

        $this->assertNull($check('anything'));
    }

    public function testCheckWithGivesTheMessageOfTheException(): void
    {
        $check = Questions::checkWith(static function (string $answer): void {
            throw new Exception('refused ' . $answer);
        });

        $this->assertSame('refused x', $check('x'));
    }

    public function testNamespaceAcceptsTheDefault(): void
    {
        $this->answers("\n");

        $this->assertSame('App', Questions::namespace($this->interactive(), 'App'));
        $this->assertSame('Root namespace [App]: ', $this->readStderr());
    }

    public function testNamespaceAsksAgainForAnUnusableAnswer(): void
    {
        $this->answers("1bad\nShop\n");

        $this->assertSame('Shop', Questions::namespace($this->interactive(), 'App'));
        $this->assertStringContainsString("'1bad' is not a usable namespace", $this->readStderr());
    }

    public function testNamespaceDropsSurroundingBackslashes(): void
    {
        $this->answers("\\Shop\\\n");

        $this->assertSame('Shop', Questions::namespace($this->interactive(), 'App'));
    }

    public function testNamespaceInAProjectMustBeInThePsr4Map(): void
    {
        $this->answers("Shop\nApp\\Shop\n");

        $this->assertSame('App\\Shop', Questions::namespace($this->interactive(), 'App', $this->root));
        $this->assertStringContainsString("'Shop' is not in the psr-4 map of composer.json", $this->readStderr());
    }

    /**
     * @dataProvider badFolders
     */
    public function testPathAsksAgainForABadFolder(string $answer): void
    {
        $this->answers($answer . "\nsrc/Handlers\n");

        $this->assertSame(
            'src/Handlers',
            Questions::path($this->interactive(), $this->root, 'action', 'src/Action')
        );
        $this->assertStringContainsString(
            "'" . $answer . "' must be a relative folder in a psr-4 folder of composer.json, e.g. 'src/Action'",
            $this->readStderr()
        );
    }

    /**
     * @dataProvider badNames
     */
    public function testPathAsksAgainForAFolderThatIsNotAPhpName(string $answer, string $namespace): void
    {
        // The first make:* would write a class with this namespace.
        $this->answers($answer . "\nsrc/Handlers\n");

        $this->assertSame(
            'src/Handlers',
            Questions::path($this->interactive(), $this->root, 'action', 'src/Action')
        );
        $this->assertStringContainsString(
            "'" . $answer . "' gives the namespace '" . $namespace . "', which is not usable; "
            . "use folder names that are PHP names, e.g. 'src/Action'",
            $this->readStderr()
        );
    }

    public function testPathRemovesALeadingDotSlash(): void
    {
        $this->answers("./src/Handlers\n");

        $this->assertSame(
            'src/Handlers',
            Questions::path($this->interactive(), $this->root, 'action', 'src/Action')
        );
    }

    public function testPathRemovesTheSlashAtTheEnd(): void
    {
        $this->answers("src/Handlers/\n");

        $this->assertSame(
            'src/Handlers',
            Questions::path($this->interactive(), $this->root, 'action', 'src/Action')
        );
    }

    public function testPathSaysIfTheFolderExists(): void
    {
        $this->answers("\n\n");

        $this->assertSame(
            'src/Action',
            Questions::path($this->interactive(), $this->root, 'action', 'src/Action')
        );
        $this->assertSame(
            'src/Command',
            Questions::path($this->interactive(), $this->root, 'command', 'src/Command')
        );
        $this->assertSame(
            'action folder (exists) [src/Action]: command folder (does not exist yet) [src/Command]: ',
            $this->readStderr()
        );
    }

    public function testRuntimeAGivenServiceAsksOnlyTheType(): void
    {
        $this->answers("docker\n");

        $this->assertSame('web', Questions::runtime($this->interactive(), Runtime::docker(), null, 'web')->service);
        $this->assertSame('Runtime (host, docker) [docker]: ', $this->readStderr());
    }

    public function testRuntimeAGivenServiceIsChecked(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'bad!' is not a usable service name");

        Questions::runtime($this->interactive(), Runtime::docker(), 'docker', 'bad!');
    }

    public function testRuntimeAGivenTypeAsksOnlyTheService(): void
    {
        $this->answers("web\n");

        $this->assertSame('web', Questions::runtime($this->interactive(), Runtime::docker(), 'docker')->service);
        $this->assertSame('Docker compose service [app]: ', $this->readStderr());
    }

    public function testRuntimeAGivenTypeIgnoresTheCase(): void
    {
        $this->assertFalse(Questions::runtime($this->interactive(), Runtime::docker(), 'HOST')->isDocker());
    }

    public function testRuntimeAnUnknownGivenTypeIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown runtime 'podman'; expected host or docker");

        Questions::runtime($this->interactive(), Runtime::docker(), 'podman');
    }

    public function testRuntimeAsksAgainForAnUnusableService(): void
    {
        $this->answers("docker\n-bad\nweb\n");

        $this->assertSame('web', Questions::runtime($this->interactive(), Runtime::host())->service);
        $this->assertStringContainsString("'-bad' is not a usable service name", $this->readStderr());
    }

    public function testRuntimeDefaultsComeFromTheProposal(): void
    {
        $this->answers("\n\n");

        $runtime = Questions::runtime($this->interactive(), Runtime::docker('web'));

        $this->assertTrue($runtime->isDocker());
        $this->assertSame('web', $runtime->service);
        $this->assertSame(
            'Runtime (host, docker) [docker]: Docker compose service [web]: ',
            $this->readStderr()
        );
    }

    public function testRuntimeDockerAsksForTheService(): void
    {
        $this->answers("docker\nweb\n");

        $runtime = Questions::runtime($this->interactive(), Runtime::host());

        $this->assertTrue($runtime->isDocker());
        $this->assertSame('web', $runtime->service);
    }

    public function testRuntimeHostAsksNoService(): void
    {
        $this->answers("host\n");

        $this->assertFalse(Questions::runtime($this->interactive(), Runtime::docker())->isDocker());
        $this->assertSame('Runtime (host, docker) [docker]: ', $this->readStderr());
    }

    public function testWithoutInteractionTheProposalsAreTheAnswers(): void
    {
        $output = new Output($this->stdout, $this->stderr, false, $this->stdin, false);

        $this->assertSame('Shop', Questions::namespace($output, 'Shop'));
        $this->assertSame('Shop\\AppFront', Questions::bootstrap($output, 'Shop\\AppFront'));
        $this->assertSame('web', Questions::runtime($output, Runtime::docker('web'))->service);
        $this->assertSame('', $this->readStdout());
        $this->assertSame('', $this->readStderr());
    }

    private function interactive(): Output
    {
        return new Output($this->stdout, $this->stderr, false, $this->stdin, true);
    }
}
