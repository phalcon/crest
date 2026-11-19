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

Crest is a command line application for Phalcon. It generates code, shows information
about a project, and creates and runs projects. For the two flavors of project that Crest
works with, MVC (Model-View-Controller) and ADR (Action-Domain-Responder), see
[Overview](docs/index.md#overview).

## Requirements

- PHP `^8.1`, with the `tokenizer` extension (`ext-tokenizer`; PHP turns it on by default)
- Phalcon v5, the `ext-phalcon` C extension (`^5.18`), or Phalcon v6, the
  `phalcon/phalcon` PHP implementation (`^6`). Crest itself needs neither of them to run

## Install

Crest has no tagged release yet. Packagist has only the version `dev-master`, and the
default `minimum-stability` of composer is `stable`. Thus give the version in the
`composer require` command.

In a project, install Crest as a dev dependency:

```bash
composer require --dev phalcon/crest:dev-master
```

This copy is the project Crest, in `vendor/bin/crest`. It runs the commands that work on
the project, for example the generators (the `make:*` commands). See
[The Global Crest and the Project Crest](docs/index.md#the-global-crest-and-the-project-crest).

To create projects, also install Crest globally. This copy is the global Crest. The global
composer `vendor/bin` folder must be in your `PATH`:

```bash
composer global require phalcon/crest:dev-master
```

## Quick start

`crest new` creates a project (see [Creating a Project](docs/index.md#creating-a-project)). Run it with
the global Crest of [Install](#install). On a host with docker and no PHP, use
[With Docker Only](#with-docker-only) instead.

```bash
crest new my-app                      # create an MVC project
crest new my-app --flavor=adr         # create an ADR project
```

In a terminal, `new` asks for each value that no option gives. An empty answer takes the
default (see [Creating a Project](docs/index.md#creating-a-project)).

For an MVC project with no database, the output of `new` gives two ways to run the
project. With docker, you use `./crest`, a script that `crest new` writes into the project
(see [The Launcher](docs/index.md#the-launcher)). With PHP on the host, you use `crest serve`. The next
two sections explain each way. The output is:

```text
Created my-app/

Nothing runs it yet. With docker:

    cd my-app
    ./crest up
    ./crest install

Or with PHP and composer on the host:

    cd my-app
    composer install
    crest serve

Then GET / answers from src/Controllers/IndexController.php
```

### With Docker

With docker on the host, run:

```bash
cd my-app
./crest up
./crest install
```

`./crest up` starts the containers. `./crest install` installs the composer dependencies
(see [The Launcher](docs/index.md#the-launcher)). A v5 project compiles the Phalcon extension in
`./crest up`. This takes some minutes. Then `GET /` answers on http://localhost:8080/.

### With PHP on the host

Without docker, create the project with `crest new my-app --runtime=host`: the project
Crest then runs with the PHP of the host (see [The Runtime](docs/index.md#the-runtime)). With PHP and
composer on the host, and no docker, run:

```bash
cd my-app
composer install
crest serve
```

`crest serve` starts PHP's built-in web server for the project (see
[Running a Project](docs/index.md#running-a-project)). By default, `crest serve` listens on
`127.0.0.1:8080`.

### With Docker only

On a host with docker and no PHP, one command creates a project, starts it and installs
it, as in [With Docker](#with-docker). The command downloads the
[`crest-setup` script](docs/index.md#the-setup-script) and runs it. `my-app` is the name of the
project:

```bash
curl -fsSL https://phalcon.io/crest-setup | sh -s -- my-app
```

Then `GET /` answers on http://localhost:8080/. On Windows, run the command in the
Windows Subsystem for Linux (WSL2).

For another port, set `APP_PORT`. The script writes it into the project `.env`, the file
of settings that docker compose reads (see [The Launcher](docs/index.md#the-launcher)):

```bash
curl -fsSL https://phalcon.io/crest-setup | APP_PORT=9000 sh -s -- my-app
```

[The Setup Script](docs/index.md#the-setup-script) gives the details: the options, the errors, and how
to read the script before it runs.

## Documentation

The full documentation is in [docs/index.md](docs/index.md). The same text is on the
Phalcon site: <https://docs.phalcon.io/6.0/crest/>.

## Development

```bash
docker compose up -d
docker exec crest-app composer install
docker exec crest-app composer test
docker exec crest-app composer cs
docker exec crest-app composer analyze
```

Set `PHALCON_VARIANT=v6` in `.env` and rebuild to test against `phalcon/phalcon` instead
of the C extension.

### Database tests

`make:model` reads the database of the project. Its tests are in `tests/Database`, and they run on
SQLite, MySQL 8.0, MariaDB 10.6 and PostgreSQL 18. `docker compose up -d` starts the servers as the
services `mysql`, `mariadb` and `postgres`. The `DATA_*` lines of `resources/.env.example` tell the
tests where the servers are: copy them into `.env`, then run
`docker compose up -d --build --force-recreate`, so that `crest-app` gets the new values.

```bash
docker exec crest-app composer test-db
docker exec crest-app composer test-db-sqlite
```

`test-db` runs the four suites. `test-db-sqlite`, `test-db-mysql`, `test-db-mariadb` and
`test-db-pgsql` run one suite. `composer test` runs no database test. `composer test-coverage` and
Infection run the unit tests and the SQLite tests in one run (`resources/full/phpunit.xml`).

**Warning:** a database suite removes each table of its database, before and after each test. The
`DATA_*` values must name a database that only the tests use. Run one suite at a time on a database.
A MySQL, MariaDB or PostgreSQL suite stops when its `DATA_*_NAME` value is not set.

A container built with `PHALCON_VARIANT=v6` has no C extension, so the commands above run the v6
path. To run the v6 path in the v5 container, use `php -n`. `php -n` does not load the C extension,
`pdo_mysql` or `pdo_pgsql`, so load the PDO driver with `-d extension=`:

```bash
docker exec crest-app php -n vendor/bin/phpunit -c resources/phpunit.sqlite.xml
docker exec crest-app php -n -d extension=pdo_mysql vendor/bin/phpunit -c resources/phpunit.mysql.xml
docker exec crest-app php -n -d extension=pdo_mysql vendor/bin/phpunit -c resources/phpunit.mariadb.xml
docker exec crest-app php -n -d extension=pdo_pgsql vendor/bin/phpunit -c resources/phpunit.pgsql.xml
```

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
