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

use Crest\Generator\Project\AdrSkeleton;
use Crest\Generator\Project\Answers;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Flavor;
use Crest\Project\Runtime;
use PHPUnit\Framework\TestCase;

final class AdrSkeletonTest extends TestCase
{
    public function testItHasNoEngines(): void
    {
        $this->assertFalse((new AdrSkeleton())->engines()->hasEngines());
    }

    public function testItHasNoFilesOfItsOwn(): void
    {
        // project-front and project-readme are in NewCommand::FILES: the
        // stub lookup finds the adr copy.
        $this->assertSame([], (new AdrSkeleton())->files($this->answers()));
    }

    public function testItIsTheAdrFlavor(): void
    {
        $this->assertSame(Flavor::ADR, (new AdrSkeleton())->flavor());
    }

    public function testTheEntryIsTheSeedAction(): void
    {
        $this->assertSame('src/Action/Get.php', (new AdrSkeleton())->entry());
    }

    public function testTheSeedIsTheGetActionFromTheActionStub(): void
    {
        $stub = new Stub(Paths::stubs());

        $this->assertSame(
            [
                'src/Action/Get.php' => $stub->render(
                    'adr',
                    'action',
                    [
                        'attributes' => '',
                        'class'      => 'Get',
                        'namespace'  => 'Acme\\Shop\\Action',
                        'params'     => '',
                    ]
                ),
            ],
            (new AdrSkeleton())->seed($stub, $this->answers())
        );
    }

    public function testTheValuesNameTheActions(): void
    {
        $this->assertSame(
            [
                'actionNamespace' => 'Acme\\Shop\\Action',
                'actionPath'      => 'src/Action',
                'seed'            => 'Get',
            ],
            (new AdrSkeleton())->values($this->answers())
        );
    }

    private function answers(): Answers
    {
        return new Answers('shop', Flavor::ADR, 'Acme\\Shop', '8.4', 'v5', Runtime::docker(), null);
    }
}
