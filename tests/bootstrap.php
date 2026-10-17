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

use Phalcon\Talon\Bootstrap\Runner;
use Phalcon\Talon\Settings;

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

// The phpunit file gives the SQLite file from the project root. The tests
// change the working directory, so talon gets the absolute path. Each
// parallel process of Infection has a TEST_TOKEN, and gets its own file.
$overrides = [];
$sqlite    = getenv('DATA_SQLITE_NAME');

if (false !== $sqlite && '' !== $sqlite) {
    $token = getenv('TEST_TOKEN');

    if (false !== $token && '' !== $token) {
        $sqlite = str_replace('.sqlite', '-' . $token . '.sqlite', $sqlite);
    }

    $overrides['DATA_SQLITE_NAME'] = $root . '/' . $sqlite;
}

// A server suite removes each table of its database. With no database name,
// talon gives its default database, so the suite stops.
$names = [
    'mariadb' => 'DATA_MARIADB_NAME',
    'mysql'   => 'DATA_MYSQL_NAME',
    'pgsql'   => 'DATA_POSTGRES_NAME',
];
$name  = $names[(string) getenv('driver')] ?? '';

if ('' !== $name && in_array(getenv($name), [false, ''], true)) {
    throw new RuntimeException(
        $name . ' is not set. Copy the DATA_* lines of resources/.env.example into .env.'
    );
}

Runner::for(Settings::fromEnv($overrides))->boot();
