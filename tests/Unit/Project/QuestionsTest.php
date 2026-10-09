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

namespace Crest\Tests\Unit\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Project\Questions;
use Crest\Project\Runtime;
use Crest\Tests\Support\CapturesOutput;
use PHPUnit\Framework\TestCase;

final class QuestionsTest extends TestCase
{
    use CapturesOutput;

    protected function setUp(): void
    {
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
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
        $this->assertSame('Front controller class (empty for none): ', $this->readStdout());
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
        $this->assertSame('Root namespace [App]: ', $this->readStdout());
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
            $this->readStdout()
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
        $this->assertSame('Runtime (host, docker) [docker]: ', $this->readStdout());
    }

    public function testWithoutInteractionTheProposalsAreTheAnswers(): void
    {
        $output = new Output($this->stdout, $this->stderr, false, $this->stdin, false);

        $this->assertSame('Shop', Questions::namespace($output, 'Shop'));
        $this->assertSame('Shop\\AppFront', Questions::bootstrap($output, 'Shop\\AppFront'));
        $this->assertSame('web', Questions::runtime($output, Runtime::docker('web'))->service);
        $this->assertSame('', $this->readStdout());
    }

    private function interactive(): Output
    {
        return new Output($this->stdout, $this->stderr, false, $this->stdin, true);
    }
}
