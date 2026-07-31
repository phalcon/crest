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
- Phalcon, either the `ext-phalcon` C extension (`^5`) or the `phalcon/phalcon` PHP
  implementation (`^6`) - crest itself needs neither to run

## Install

    composer require --dev phalcon/crest

## Usage

    vendor/bin/crest                      list available commands
    vendor/bin/crest about                environment and version report
    vendor/bin/crest make:action GET /company/all

## Global options

| Option | Purpose |
|---|---|
| `--config=<file>` | explicit path to `crest.php` |
| `--directory=<dir>` | project root override |
| `--trace` | full exception trace |
| `--help`, `-h` | usage for the current command |
| `--quiet`, `-q` | suppress non-essential output |
| `--version` | crest version |

## Configuration

`crest.php` at the project root is optional. Without it, crest reads `composer.json`'s
psr-4 map and defaults `paths.action` to `src/Action`.

```php
return [
    'flavor'    => 'adr',
    'namespace' => 'App',
    'paths'     => ['action' => 'src/Action'],
];
```

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
