<?php

/*
 * Suite map for the talon test runner.
 */

declare(strict_types=1);

return [
    'suites'  => [
        'unit' => ['config' => 'resources/phpunit.xml.dist'],
    ],
    'default' => 'unit',
];
