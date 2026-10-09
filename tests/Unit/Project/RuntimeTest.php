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
use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase
{
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
        $this->expectExceptionMessage("unknown runtime ''; expected host or docker");

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
