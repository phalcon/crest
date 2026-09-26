# Changelog

All notable changes are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## [Unreleased]

### Added

- Added `route:list`: lists routes with their method and Action class. [#1](https://github.com/phalcon/crest/issues/1)
- Added `config:show`: shows the resolved configuration, each value marked declared or inferred. [#1](https://github.com/phalcon/crest/issues/1)
- Added `container:list`: lists container services with their class and resolved state. [#1](https://github.com/phalcon/crest/issues/1)
- Added `event:list`: lists the events manager listeners. [#1](https://github.com/phalcon/crest/issues/1)
- Added `list` (aliases `commands`, `enumerate`). [#1](https://github.com/phalcon/crest/issues/1)
- Added the `bootstrap` key to `crest.php`: the front controller class to boot, e.g. `App\Front\AppFront::class`. Its `boot()` must return a container. [#1](https://github.com/phalcon/crest/issues/1)
- Added `Crest\Console\Input::argumentString()`, `optionString()` and `optionStringOrNull()`.
- Added `make:command`: generates a crest command and prints its `extra.crest.commands` entry. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:middleware`: generates an ADR middleware and prints its middleware-map entry. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:provider`: generates a `Phalcon\Container` service provider and prints the `registerProviders()` override. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:responder`: generates an ADR responder that implements `Responder`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `stub:publish`: copies packaged stubs to `resources/stubs/<flavor>/`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:action --stub`: renders a named stub instead of the `--responder` default. Using both is an error. [#5](https://github.com/phalcon/crest/issues/5)
- Added `command`, `middleware`, `provider` and `responder` to the default `paths`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Command\ProjectCommand`: base for commands that read a project; resolves `--directory` and `--config`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Generator\ClassName::suffixed()`: appends a suffix once (`Cors` and `CorsMiddleware` both give `CorsMiddleware`). [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:action --template`: overrides the view template (default `<path>/index`). [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Command\ProjectCommand::writer()`: builds the stub writer for the `make:*` commands. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\ADR\ActionResolver::methodFor()`: gets an Action's HTTP method from the router. [#1](https://github.com/phalcon/crest/issues/1)
- Added `Crest\Console\Registry::descriptions()` and `Crest\Console\Output::commandTable()`: one listing for `crest` and `crest list`.
- Added `new`: creates an ADR project from stubs (front controller, `public/index.php`, `crest.php`, a `GET /` action, `composer.json`, docker files). Runs no composer, docker or network. Options: `--namespace`, `--php`, `--phalcon`. The project requires `phalcon/crest` in `require-dev`; project commands run as `vendor/bin/crest`. Writes nothing if a stub fails to render. [#8](https://github.com/phalcon/crest/issues/8)
- Added `up`, `down` and `install`: `docker compose up -d`, `docker compose down` and `composer install` in the `app` container. `--directory` names the project. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Process\Runner` and `ShellRunner`: run external programs. A missing program or directory is a crest error. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Generator\ClassName::namespace()`: validates a namespace. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Command\Make\NamedArtifactCommand`: base of `make:command`, `make:middleware`, `make:provider` and `make:responder`.
- Added `serve` (alias `server`): runs `php -S 127.0.0.1:8080 -t public .htrouter.php` in the project root. Port: `--port`, then `APP_PORT` (environment or `.env`), then 8080. Fails if `.htrouter.php` or `vendor/autoload.php` is missing. [#10](https://github.com/phalcon/crest/issues/10)
- Added a package hint for unknown commands: `unknown command 'migration:run'; provided by phalcon/migrations`. No hint when a command with that prefix is registered. The map is set with `Crest\Console\Registry::withProviders()`. [#19](https://github.com/phalcon/crest/issues/19)

### Changed

- `make:action` writes a `params()` declaration for routes with attributes.
- `make:action` rejects a static segment after a placeholder: `/album/{id}/edit` must be `/album/edit/{id}`.
- Renamed `Crest\Adr` to `Crest\ADR`, and the `Flavor` cases to `ADR`, `CLI` and `MVC`. Backed values are unchanged.
- Renamed `Crest\ADR\CandidateSource` to `ActionResolver`, and `PhalconRouterCandidates` to `PhalconRouterResolver`.
- Dependencies resolve against PHP 8.1 (`config.platform`).
- Default `paths` are per flavor. Only `adr` has defaults. [#5](https://github.com/phalcon/crest/issues/5)
- `crest`, `crest list` and `crest --version` show a chevron before the name. No color when piped or with `NO_COLOR`. [#5](https://github.com/phalcon/crest/issues/5)
- `make:action --responder=view` prints the template name. [#5](https://github.com/phalcon/crest/issues/5)
- `route:list` and `make:action` take an optional `ActionResolver`. [#5](https://github.com/phalcon/crest/issues/5)
- `event:list` reads all listeners with one `getListenerMap()` call. [#1](https://github.com/phalcon/crest/issues/1)
- `phalcon/talon` moved from `^0.8` to `^1.0.0`.
- `stub:publish` without a name skips the `project-*` stubs. Publish them by name; they go to the working directory or `--directory`. [#8](https://github.com/phalcon/crest/issues/8)
- Rendering a stub fails when a placeholder has no value. The error names the stub. [#8](https://github.com/phalcon/crest/issues/8)
- The config inference error names the missing psr-4 directories. [#18](https://github.com/phalcon/crest/issues/18)

### Fixed

- `make:middleware`, `make:provider` and `make:responder` generate valid code when the name is the suffix (`make:middleware Middleware`). [#5](https://github.com/phalcon/crest/issues/5)
- Generators exit 1 with `could not create <directory>` when the target cannot be created. [#5](https://github.com/phalcon/crest/issues/5)
- `stub:publish` rejects a name that is a path. [#5](https://github.com/phalcon/crest/issues/5)
- `ClassName::suffixed()` accepts non-Latin class names. [#5](https://github.com/phalcon/crest/issues/5)
- `container:list` and `event:list` type against Phalcon's published contracts (`Phalcon\Contracts\Container\Service\Collection`, `Enumerable`), not `Container` and `Manager`. [#1](https://github.com/phalcon/crest/issues/1)
- `route:list` gets the HTTP method from the router, not from the class name. [#1](https://github.com/phalcon/crest/issues/1)

### Removed

- Removed the shadowed-action warning from `make:action`.
- Removed the `phalcon/cli-options-parser` requirement.

[keep_a_changelog]: https://keepachangelog.com/en/1.0.0/
[semantic_versioning]: https://semver.org/spec/v2.0.0.html
[Unreleased]: https://github.com/phalcon/crest/commits/master
