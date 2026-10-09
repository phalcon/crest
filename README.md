# Phalcon Crest

[![Latest Version][packagist-version-badge]][packagist-version-link]
[![PHP Version][php-version-badge]][packagist-version-link]
[![Total Downloads][packagist-downloads-badge]][packagist-downloads-link]
[![License][license-badge]][license-link]

[![Crest CI][crest-ci-badge]][crest-ci-link]
[![Quality Gate Status][sonar-quality-badge]][sonar-link]
[![Coverage][sonar-coverage-badge]][sonar-link]
[![PDS Skeleton][pds-skeleton-badge]][pds-skeleton-link]

[![Discord][discord-badge]][discord-link]
[![Contributors][contributors-badge]][contributors-link]
[![OpenCollective Backers][oc-backers-badge]][oc-backers-link]
[![OpenCollective Sponsors][oc-sponsors-badge]][oc-sponsors-link]

Command line application for Phalcon - generators, introspection and project tooling.

## Requirements

- PHP `^8.1`
- Phalcon, either the `ext-phalcon` C extension (`^5.18`) or the `phalcon/phalcon` PHP
  implementation (`^6`) - crest itself needs neither to run

## Install

In a project:

    composer require --dev phalcon/crest

To create projects, also install crest globally. The global composer `vendor/bin`
directory must be in your `PATH`:

    composer global require phalcon/crest

## Usage

Run `crest` in a project. The commands that work on the project need the project
autoloader and its Phalcon, so a global crest passes them to the project's
`vendor/bin/crest` and returns its exit status:

    crest                                 list available commands
    crest about                           environment and version report
    crest make:action GET /company/all

`new`, `init`, `up`, `down`, `install` and `--version` always run in the crest that you
type. Without a global crest, run `vendor/bin/crest` in the project.

crest finds the project by its `crest.php`: the file that `--config` names, or the
nearest `crest.php` above the working directory or `--directory`. Without `crest.php`,
crest stops and tells you to run `crest init`. If the project has no `vendor/bin/crest`
yet, crest stops and tells you to run `crest install` or `composer install`, or
`composer require --dev phalcon/crest` when the project does not require crest.

To create a project, use the global crest. The new project requires `phalcon/crest`, so
after `composer install` it has its own `vendor/bin/crest`:

    crest new my-app                      create an ADR project

In a terminal, `new` asks for each value that no option gives. `--no-interaction` takes
the defaults:

| Option | Purpose |
|---|---|
| `--namespace=<name>` | root namespace for the generated code; defaults to `App` |
| `--php=<major.minor>` | PHP version for `composer.json` and the Dockerfile; defaults to `8.4`, and must be 8.1 or later |
| `--phalcon=v5\|v6` | `v5` requires the C extension, 5.18 or later; `v6` the `phalcon/phalcon` package; defaults to `v5` |
| `--runtime=host\|docker` | where the project commands run (the `runtime` key of `crest.php`); defaults to `docker` |
| `--service=<name>` | the docker compose service, for `docker`; defaults to `app` |
| `--force` | write into a directory that is not empty, and overwrite files with the same names |

For an existing project, `crest init` writes `crest.php`:

    crest init                            write crest.php for this project

## Global options

| Option | Purpose |
|---|---|
| `--config=<file>` | explicit path to `crest.php` |
| `--directory=<dir>` | where crest starts to look for `crest.php`; for `new` and `init`, the directory to use |
| `--trace` | full exception trace |
| `--help`, `-h` | usage for the current command |
| `--quiet`, `-q` | suppress non-essential output |
| `--no-interaction`, `-n` | ask no questions; use the default answers |
| `--version` | crest version |

## Configuration

Each project has a `crest.php` at its root. `crest new` writes it. For an existing
project, `crest init` writes it. `init` proposes the values that it finds in the project:
the namespace and the folder of the first psr-4 entry whose folder exists, the front
controller `<namespace>\AppFront` when `AppFront.php` is in that folder, and the docker
runtime when the project has a compose file. It asks you to confirm each one.
`--no-interaction` accepts the proposals. `--force` overwrites an existing `crest.php`.

```php
return [
    'flavor'    => 'adr',
    'namespace' => 'App',
    'bootstrap' => App\AppFront::class,
    'paths'     => [
        'action'     => 'src/Action',
        'command'    => 'src/Command',
        'middleware' => 'src/Middleware',
        'provider'   => 'src/Provider',
        'responder'  => 'src/Responder',
    ],
    'runtime'   => ['type' => 'docker', 'service' => 'app'],
];
```

A key that is not in the file takes its default: `flavor` `adr`, `namespace` `App`, the
ADR paths above, and the host runtime.

`runtime` says where the project commands run. With `['type' => 'host']`, a global crest
runs the project's `vendor/bin/crest` with the PHP of the host. With
`['type' => 'docker', 'service' => 'app']`, it runs
`docker compose exec app vendor/bin/crest ...` in the project root. Use docker when
Phalcon is only in the container, for example a v5 project on a host without
`ext-phalcon`. The containers must be up (`crest up`). The service must mount the project
folder, with `vendor/` in it, and its `working_dir` must be the project root. The compose
file of `crest new` does this. `crest install` runs composer in the same service. With the
host runtime, it uses the service `app`; run `composer install` on the host instead.
`crest serve` always runs on the host: PHP's built-in server must listen there. With
docker, `crest up` serves the project.

Namespaces are resolved from your psr-4 map, so a path must be covered by an autoload rule -
`src/Action` under `App\ => src/` becomes `App\Action`. If you write to a directory your
autoloader does not cover, declare the namespace outright:

```php
return [
    'paths'      => ['action' => 'app/Handlers'],
    'namespaces' => ['action' => 'Shop\Handlers'],
];
```

## Booting the project

`container:list` and `event:list` report services and listeners, which exist only once the
application has registered them, so those two start your front controller. Name it in
`crest.php`:

```php
return [
    'bootstrap' => App\Front\ApiFront::class,
];
```

The class is constructed with the project root and has to declare `boot()`. There is no
base class and no interface - `boot()` is the whole contract, and what it returns has to
implement `Phalcon\Contracts\Container\Service\Collection`, which
`Phalcon\Container\Container` does. See [docs/index.md](docs/index.md) for the rest.

Every other command - the generators, `about`, `config:show` and `route:list` - reads the
filesystem and keeps working on a project that does not currently run.

## Custom stubs

Copy a stub into `resources/stubs/<flavor>/` in your project and crest uses yours instead
of the packaged one.

The `project-*` stubs that `new` renders are published by name only. See
[docs/index.md](docs/index.md#creating-a-project).

## Development

    docker compose up -d
    docker exec crest-app composer install
    docker exec crest-app composer test
    docker exec crest-app composer cs
    docker exec crest-app composer analyze

Set `PHALCON_VARIANT=v6` in `.env` and rebuild to test against `phalcon/phalcon` instead
of the C extension.

## License

BSD-3-Clause. See [LICENSE](LICENSE).

<!-- Badges -->
[packagist-version-badge]:   https://img.shields.io/packagist/v/phalcon/crest?include_prereleases&style=flat-square&logo=packagist&logoColor=white
[packagist-version-link]:    https://packagist.org/packages/phalcon/crest
[packagist-downloads-badge]: https://img.shields.io/packagist/dt/phalcon/crest?style=flat-square&logo=packagist&logoColor=white
[packagist-downloads-link]:  https://packagist.org/packages/phalcon/crest/stats
[php-version-badge]:         https://img.shields.io/packagist/php-v/phalcon/crest?style=flat-square&logo=php&logoColor=white
[license-badge]:             https://img.shields.io/github/license/phalcon/crest?style=flat-square&logo=opensourceinitiative&logoColor=white
[license-link]:              https://github.com/phalcon/crest/blob/master/LICENSE
[crest-ci-badge]:            https://github.com/phalcon/crest/actions/workflows/main.yml/badge.svg?branch=master
[crest-ci-link]:             https://github.com/phalcon/crest/actions/workflows/main.yml
[sonar-quality-badge]:       https://sonarcloud.io/api/project_badges/measure?project=phalcon_crest&metric=alert_status
[sonar-coverage-badge]:      https://sonarcloud.io/api/project_badges/measure?project=phalcon_crest&metric=coverage
[sonar-link]:                https://sonarcloud.io/summary/new_code?id=phalcon_crest
[pds-skeleton-badge]:        https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat-square
[pds-skeleton-link]:         https://github.com/php-pds/skeleton
[discord-badge]:             https://img.shields.io/discord/310910488152375297?label=Discord&logo=discord&style=flat-square
[discord-link]:              https://phalcon.io/discord
[contributors-badge]:        https://img.shields.io/github/contributors/phalcon/crest?style=flat-square&logo=github&logoColor=white
[contributors-link]:         https://github.com/phalcon/crest/graphs/contributors
[oc-backers-badge]:          https://img.shields.io/opencollective/backers/phalcon?style=flat-square&logo=opencollective&logoColor=white
[oc-backers-link]:           https://opencollective.com/phalcon
[oc-sponsors-badge]:         https://img.shields.io/opencollective/sponsors/phalcon?style=flat-square&logo=opencollective&logoColor=white
[oc-sponsors-link]:          https://opencollective.com/phalcon
