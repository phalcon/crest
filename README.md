# Phalcon Crest

Command line application for Phalcon — generators, introspection and project tooling.

## Requirements

- PHP `^8.1`
- Phalcon, either the `ext-phalcon` C extension (`^5`) or the `phalcon/phalcon` PHP
  implementation (`^6`) — crest itself needs neither to run

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

Namespaces are resolved from your psr-4 map, so a path must be covered by an autoload rule —
`src/Action` under `App\ => src/` becomes `App\Action`. If you write to a directory your
autoloader does not cover, declare the namespace outright:

```php
return [
    'paths'      => ['action' => 'app/Handlers'],
    'namespaces' => ['action' => 'Shop\Handlers'],
];
```

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
