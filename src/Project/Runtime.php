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

namespace Crest\Project;

use Crest\Console\Exceptions\Exception;

use function is_array;
use function is_string;
use function sprintf;
use function var_export;

/**
 * Where the project commands run: on the host, or in a docker compose
 * service. The `runtime` key of crest.php states it:
 *
 *   'runtime' => ['type' => 'docker', 'service' => 'app'],
 *
 * No key means the host. The service is also the service of `install`, so a
 * host runtime keeps the default service.
 */
final class Runtime
{
    public const DOCKER = 'docker';

    public const HOST = 'host';

    /**
     * The service that `crest new` writes into docker-compose.yml.
     */
    public const SERVICE = 'app';

    private function __construct(
        public readonly string $type,
        public readonly string $service,
    ) {
    }

    public static function docker(string $service = self::SERVICE): self
    {
        return new self(self::DOCKER, $service);
    }

    /**
     * The value of the `runtime` key, as crest.php states it. Null when there
     * is no key.
     */
    public static function fromConfig(mixed $declared): self
    {
        if (null === $declared) {
            return self::host();
        }

        if (false === is_array($declared)) {
            throw new Exception("'runtime' must be an array, e.g. ['type' => 'docker', 'service' => 'app']");
        }

        $type    = $declared['type'] ?? null;
        $service = $declared['service'] ?? null;
        $service = true === is_string($service) && '' !== $service ? $service : self::SERVICE;

        return match ($type) {
            self::DOCKER => self::docker($service),
            self::HOST   => self::host(),
            default      => throw new Exception(
                sprintf("unknown runtime '%s'; expected host or docker", true === is_string($type) ? $type : '')
            ),
        };
    }

    public static function host(): self
    {
        return new self(self::HOST, self::SERVICE);
    }

    public function isDocker(): bool
    {
        return self::DOCKER === $this->type;
    }

    /**
     * The value as PHP code, for the crest.php that crest writes.
     */
    public function render(): string
    {
        if (false === $this->isDocker()) {
            return "['type' => 'host']";
        }

        return sprintf("['type' => 'docker', 'service' => %s]", var_export($this->service, true));
    }
}
