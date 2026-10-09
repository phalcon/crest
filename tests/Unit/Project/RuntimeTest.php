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
use Crest\Project\Runtime;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('runtime');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    public function testADockerRuntimeNamesItsService(): void
    {
        $runtime = Runtime::fromConfig(['type' => 'docker', 'service' => 'web']);

        $this->assertTrue($runtime->isDocker());
        $this->assertSame('docker', $runtime->type);
        $this->assertSame('web', $runtime->service);
    }

    public function testADockerRuntimeWithoutAServiceUsesApp(): void
    {
        $this->assertSame('app', Runtime::fromConfig(['type' => 'docker'])->service);
    }

    public function testAMissingTypeIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'runtime' needs a 'type': host or docker");

        Runtime::fromConfig(['service' => 'web']);
    }

    public function testAnEmptyServiceUsesApp(): void
    {
        $this->assertSame('app', Runtime::fromConfig(['type' => 'docker', 'service' => ''])->service);
    }

    public function testANonArrayIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'runtime' must be an array, e.g. ['type' => 'docker', 'service' => 'app']");

        Runtime::fromConfig('docker');
    }

    public function testAnUnknownTypeIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown runtime 'podman'; expected host or docker");

        Runtime::fromConfig(['type' => 'podman']);
    }

    public function testDockerRendersItsService(): void
    {
        $this->assertSame("['type' => 'docker', 'service' => 'web']", Runtime::docker('web')->render());
    }

    public function testFromFileReadsOnlyTheRuntimeKey(): void
    {
        // A newer crest can write a flavor that this crest does not know.
        $this->writeCrestPhp("['flavor' => 'future', 'runtime' => ['type' => 'docker', 'service' => 'web']]");

        $runtime = Runtime::fromFile($this->root . '/crest.php');

        $this->assertTrue($runtime->isDocker());
        $this->assertSame('web', $runtime->service);
    }

    public function testFromFileWithoutTheKeyIsTheHost(): void
    {
        $this->writeCrestPhp();

        $this->assertFalse(Runtime::fromFile($this->root . '/crest.php')->isDocker());
    }

    public function testHostRendersOnlyItsType(): void
    {
        $this->assertSame("['type' => 'host']", Runtime::host()->render());
    }

    public function testNoKeyIsTheHost(): void
    {
        $runtime = Runtime::fromConfig(null);

        $this->assertFalse($runtime->isDocker());
        $this->assertSame('host', $runtime->type);
        // install uses the service with every runtime.
        $this->assertSame('app', $runtime->service);
    }

    public function testTheHostTypeIsTheHost(): void
    {
        $this->assertFalse(Runtime::fromConfig(['type' => 'host', 'service' => 'web'])->isDocker());
    }
}
