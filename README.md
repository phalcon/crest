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

- PHP `^8.1`, with the `tokenizer` extension (`ext-tokenizer`; PHP turns it on by default)
- Phalcon, either the `ext-phalcon` C extension (`^5.18`) or the `phalcon/phalcon` PHP
  implementation (`^6`) - crest itself needs neither to run

## Install

In a project:

    composer require --dev phalcon/crest

To create projects, also install crest globally. The global composer `vendor/bin`
directory must be in your `PATH`:

    composer global require phalcon/crest

### With docker only

On a host with docker and no PHP, one command creates a project, starts it and installs
it:

    curl -fsSL https://phalcon.io/crest-setup | sh -s -- my-app

The options of `new` follow the name, for example `--phalcon v6`. `new` asks no questions
here: the options give the values, else the defaults apply. The project goes into the
folder `<name>` in the working directory. `--directory` is not supported, and a system
folder such as `/usr` is refused as the working directory. `--help` shows the options of
`new`.

The script runs `crest new` in a one-off `composer:2` container. It writes your user and
group IDs into the project `.env`, then runs `./crest up` and `./crest install`. Then
GET / answers on http://localhost:8080/. A v5 project compiles the Phalcon extension in
`./crest up`. This takes some minutes. On Windows, run it in WSL2.

For another port, set `APP_PORT`. The script writes it into the project `.env`:

    curl -fsSL https://phalcon.io/crest-setup | APP_PORT=9000 sh -s -- my-app

If `./crest up` or `./crest install` fails, for example because the port is in use, the
project stays. The script prints the commands to run after you fix the problem. With
rootless docker, the script writes `UID=0` and `GID=0` into `.env`: rootless docker maps
root in the container to your user.

Do not add a `new` key to a published `project-config` stub for this command. `new` runs
in a container without docker, so `crest up` fails there. The script runs `./crest up`
and `./crest install` itself.

The script runs on your host. To read it before it runs:

    curl -fsSL https://phalcon.io/crest-setup -o crest-setup.sh
    less crest-setup.sh
    sh crest-setup.sh my-app

`https://phalcon.io/crest-setup` points to
`https://raw.githubusercontent.com/phalcon/crest/master/resources/setup.sh`.

## Usage

Run `crest` in a project. The commands that work on the project need the project
autoloader and its Phalcon, so a global crest passes them to the project's
`vendor/bin/crest` and returns its exit status:

    crest                                 list available commands
    crest about                           environment and version report
    crest make:action GET /company/all

`new`, `init`, `up`, `down`, `install` and `--version` always run in the crest that you
type. Without a global crest, run `vendor/bin/crest` in the project, or `./crest` on a
host with docker and no PHP.

crest finds the project by its `crest.php`: the file that `--config` names, or the
nearest `crest.php` above the working directory or `--directory`. Without `crest.php`,
crest stops and tells you to run `crest init`. If the project has no `vendor/bin/crest`
yet, crest stops and tells you to run `crest install` or `composer install`, or
`composer require --dev phalcon/crest` when the project does not require crest.

To create a project, use the global crest. The new project requires `phalcon/crest`, so
after `composer install` it has its own `vendor/bin/crest`:

    crest new my-app                      create an ADR project
    crest new my-app --flavor=mvc         create an MVC project

In a terminal, `new` asks for each value that no option gives. `--no-interaction` takes
the defaults:

| Option | Purpose |
|---|---|
| `--flavor=adr\|mvc` | the flavor of the project; defaults to `adr` |
| `--template-engine=<engine>` | the view engine of an MVC project: `phtml`, the default and the only engine for now. An ADR project has no views, so `new` stops when you give it |
| `--namespace=<name>` | root namespace for the generated code; defaults to `App` |
| `--php=<major.minor>` | PHP version for `composer.json` and the Dockerfile; defaults to `8.4`, and must be 8.1 or later |
| `--phalcon=v5\|v6` | `v5` requires the C extension, 5.18 or later; `v6` the `phalcon/phalcon` package; defaults to `v5` |
| `--runtime=host\|docker` | where the project commands run (the `runtime` key of `crest.php`); defaults to `docker` |
| `--service=<name>` | the docker compose service, for `docker`; defaults to `app` |
| `--database=none\|mariadb\|mysql\|pgsql\|sqlite` | the database of the project; defaults to `none`. See [docs/index.md](docs/index.md#the-database) |
| `--camel-case` | for MVC: property names in camelCase (`models.camelCase` of `crest.php`) |
| `--force` | write into a directory that is not empty, and overwrite files with the same names |

`new` also writes `./crest`, a launcher for a host with docker and no PHP. `./crest up`,
`./crest down` and `./crest install` run docker compose on the host: they start the
containers, stop them, and install the dependencies. All other commands run
`vendor/bin/crest` in the service of the project. Before `./crest install`, they stop
with a hint. On Windows, run it in WSL2. With rootless docker, set `UID=0` and `GID=0` in
the project `.env`.

By default, `new` runs nothing: it writes the files and prints the next commands. A
published `project-config` stub can add a `new` key, with commands that `new` runs after
it writes the files, for example `crest up` and `crest install`. See
[docs/index.md](docs/index.md#commands-after-new).

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
project, `crest init` writes it. `init` proposes the values that it finds in the project,
and asks you to confirm each one. First the namespace of the first psr-4 entry whose
folder exists. Your answer must be in the psr-4 map, and the other proposals follow it:
the flavor (`adr` or `mvc`, from the default folders of each flavor that the project
has; `--flavor` answers it), the front controller `<namespace>\AppFront` when
`AppFront.php` is in the folder of the namespace (answer `none` for no front
controller), a folder for each path of the flavor (the question says if it exists), and
the docker runtime when the project has a compose file. `--no-interaction` accepts the
proposals. `--force` overwrites an existing `crest.php`.

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
host runtime, it uses the `service` of the key, else `app`.
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

`models` changes the names that `make:model` gives in an MVC project: `classes`
gives a class to a table, and `camelCase` gives camelCase property names. `crest new`
and `crest init` write the key for an MVC project, with `'camelCase' => false`;
`crest new --camel-case` gives `true`. See "Names" in [docs/index.md](docs/index.md):

```php
return [
    'models' => [
        'classes'   => ['users' => 'User'],
        'camelCase' => true,
    ],
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
of the packaged one. A stub that all flavors use, for example `command.stub`, is in
`resources/stubs/shared/`. Crest looks for a stub in this order: your
`resources/stubs/<flavor>/`, your `resources/stubs/shared/`, then the same two folders in
the package. `crest stub:publish` copies each stub into the folder where crest looks for
it.

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

### Database tests

`make:model` reads the database of the project. Its tests are in `tests/Database`, and they run on
SQLite, MySQL 8.0, MariaDB 10.6 and PostgreSQL 18. `docker compose up -d` starts the servers as the
services `mysql`, `mariadb` and `postgres`. The `DATA_*` lines of `resources/.env.example` tell the
tests where the servers are: copy them into `.env`, then run
`docker compose up -d --build --force-recreate`, so that `crest-app` gets the new values.

    docker exec crest-app composer test-db
    docker exec crest-app composer test-db-sqlite

`test-db` runs the four suites. `test-db-sqlite`, `test-db-mysql`, `test-db-mariadb` and
`test-db-pgsql` run one suite. `composer test` runs no database test. `composer test-coverage` and
Infection run the unit tests and the SQLite tests in one run (`resources/full/phpunit.xml`).

**Warning:** a database suite removes each table of its database, before and after each test. The
`DATA_*` values must name a database that only the tests use. Run one suite at a time on a database.
A MySQL, MariaDB or PostgreSQL suite stops when its `DATA_*_NAME` value is not set.

A container built with `PHALCON_VARIANT=v6` has no C extension, so the commands above run the v6
path. To run the v6 path in the v5 container, use `php -n`. `php -n` does not load the C extension,
`pdo_mysql` or `pdo_pgsql`, so load the PDO driver with `-d extension=`:

    docker exec crest-app php -n vendor/bin/phpunit -c resources/phpunit.sqlite.xml
    docker exec crest-app php -n -d extension=pdo_mysql vendor/bin/phpunit -c resources/phpunit.mysql.xml
    docker exec crest-app php -n -d extension=pdo_mysql vendor/bin/phpunit -c resources/phpunit.mariadb.xml
    docker exec crest-app php -n -d extension=pdo_pgsql vendor/bin/phpunit -c resources/phpunit.pgsql.xml

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
