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

namespace Crest\Tests\Unit\Generator\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Generator\Project\TemplateEngines;
use Crest\Project\Flavor;
use Crest\Tests\Support\CapturesOutput;
use PHPUnit\Framework\TestCase;

final class TemplateEnginesTest extends TestCase
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

    public function testAFlavorWithNoEnginesHasNone(): void
    {
        $engines = new TemplateEngines(Flavor::ADR, []);

        $this->assertFalse($engines->hasEngines());
        $this->assertSame([], $engines->names());
    }

    public function testAFlavorWithNoEnginesRefusesAnEngine(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("the 'adr' flavor has no views; remove --template-engine");

        (new TemplateEngines(Flavor::ADR, []))->select('phtml');
    }

    public function testAFlavorWithNoEnginesSelectsNothing(): void
    {
        $this->assertNull((new TemplateEngines(Flavor::ADR, []))->select(null));
    }

    public function testAnEngineInTheListIsSelected(): void
    {
        $this->assertSame('volt', (new TemplateEngines(Flavor::MVC, ['phtml', 'volt']))->select('volt'));
    }

    public function testAnEngineIsMadeLowercase(): void
    {
        $this->assertSame('phtml', (new TemplateEngines(Flavor::MVC, ['phtml']))->select('PHTML'));
    }

    public function testAnUnknownEngineIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown template engine 'volt'; expected phtml");

        (new TemplateEngines(Flavor::MVC, ['phtml']))->select('Volt');
    }

    public function testAnUnknownEngineNamesEachEngine(): void
    {
        $this->expectExceptionMessage("unknown template engine 'twig'; expected phtml or volt");

        (new TemplateEngines(Flavor::MVC, ['phtml', 'volt']))->select('twig');
    }

    public function testChooseAsksNothingForOneEngine(): void
    {
        $engines = new TemplateEngines(Flavor::MVC, ['phtml']);

        $this->assertSame('phtml', $engines->choose($this->interactiveOutput(), null));
        $this->assertSame('', $this->readStderr());
    }

    public function testChooseAsksNothingWhenTheOptionIsGiven(): void
    {
        $engines = new TemplateEngines(Flavor::MVC, ['phtml', 'volt']);

        $this->assertSame('volt', $engines->choose($this->interactiveOutput(), 'volt'));
        $this->assertSame('', $this->readStderr());
    }

    public function testChooseAsksWhenThereIsMoreThanOneEngine(): void
    {
        $this->answers("volt\n");

        $engines = new TemplateEngines(Flavor::MVC, ['phtml', 'volt']);

        $this->assertSame('volt', $engines->choose($this->interactiveOutput(), null));
        $this->assertSame('Template engine (phtml, volt) [phtml]: ', $this->readStderr());
    }

    public function testChooseChecksTheOption(): void
    {
        $this->expectExceptionMessage("the 'adr' flavor has no views; remove --template-engine");

        (new TemplateEngines(Flavor::ADR, []))->choose($this->interactiveOutput(), 'phtml');
    }

    public function testTheFirstEngineIsTheDefault(): void
    {
        $engines = new TemplateEngines(Flavor::MVC, ['phtml', 'volt']);

        $this->assertTrue($engines->hasEngines());
        $this->assertSame(['phtml', 'volt'], $engines->names());
        $this->assertSame('phtml', $engines->select(null));
    }

    /**
     * An interactive output: a question reads the answers of the test.
     */
    private function interactiveOutput(): Output
    {
        return new Output($this->stdout, $this->stderr, false, $this->stdin, true);
    }
}
