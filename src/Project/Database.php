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
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Di\DiInterface;
use Throwable;

use function get_debug_type;
use function sprintf;

/**
 * The database connection of an MVC project: the `db` service of its DI.
 *
 * crest makes no connection of its own. The models of the project use the
 * `db` service, so a table that crest reads through it is a table that the
 * models read. Bootstrap boots the front controller that crest.php names.
 */
final class Database
{
    private const SERVICE = 'db';

    public static function adapter(Config $config): AdapterInterface
    {
        $container = Bootstrap::container($config);

        if (false === $container instanceof DiInterface) {
            throw new Exception(
                sprintf(
                    '%s is not a Phalcon DI container; make:model needs the DI of an MVC project',
                    get_debug_type($container)
                )
            );
        }

        if (false === $container->has(self::SERVICE)) {
            throw new Exception(
                "the project container has no 'db' service; register one with a provider (crest make:provider Db)"
            );
        }

        // Phalcon connects when it makes the adapter. The message and the
        // previous exception stay, as in Bootstrap; --trace prints both.
        try {
            $adapter = $container->getShared(self::SERVICE);
        } catch (Throwable $exception) {
            throw new Exception("the 'db' service failed: " . $exception->getMessage(), previous: $exception);
        }

        if (false === $adapter instanceof AdapterInterface) {
            throw new Exception(
                sprintf("the 'db' service is %s, not a Phalcon database adapter", get_debug_type($adapter))
            );
        }

        return $adapter;
    }
}
