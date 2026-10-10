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

namespace Crest\Process;

/**
 * The arguments of `docker compose` that run a command in a service. The one
 * place for the rule that each caller must follow: -T when stdin is not a
 * terminal (CI, a pipe), because then docker compose exec must not ask for a
 * TTY.
 */
final class Compose
{
    /**
     * @param list<string>          $command     The program in the service,
     *                                           then its arguments.
     * @param bool                  $terminal    Whether stdin is a terminal.
     * @param array<string, string> $environment Variables for the program.
     *
     * @return non-empty-list<string> The arguments after `docker compose`.
     */
    public static function exec(string $service, array $command, bool $terminal, array $environment = []): array
    {
        $arguments = ['exec'];

        foreach ($environment as $name => $value) {
            $arguments[] = '-e';
            $arguments[] = $name . '=' . $value;
        }

        if (false === $terminal) {
            $arguments[] = '-T';
        }

        return [...$arguments, $service, ...$command];
    }
}
