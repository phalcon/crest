<?php

/*
 * Suite map for the talon test runner. `unit` needs no database. Each
 * database suite runs tests/Database on its driver, and removes each table of
 * its database. `full` runs the unit tests and the SQLite tests in one run,
 * for coverage and Infection.
 */

declare(strict_types=1);

return [
    'suites'  => [
        'unit'    => ['config' => 'resources/phpunit.xml.dist'],
        'full'    => ['config' => 'resources/full/phpunit.xml'],
        'sqlite'  => ['config' => 'resources/phpunit.sqlite.xml'],
        'mysql'   => ['config' => 'resources/phpunit.mysql.xml'],
        'mariadb' => ['config' => 'resources/phpunit.mariadb.xml'],
        'pgsql'   => ['config' => 'resources/phpunit.pgsql.xml'],
    ],
    'default' => 'unit',
];
