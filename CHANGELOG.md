# Changelog

All notable changes are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## [Unreleased]

### Added

- Added `route:list`, listing every route the application answers with its method and Action class. Reads the Action classes rather than a route table, since ADR has none. [#1](https://github.com/phalcon/crest/issues/1)
- Added `config:show`, showing the resolved project configuration and marking each value as declared or inferred. [#1](https://github.com/phalcon/crest/issues/1)
- Added `container:list`, listing the services registered in the project container with their class and whether they have been resolved. [#1](https://github.com/phalcon/crest/issues/1)
- Added `event:list`, listing the listeners attached to the project events manager. [#1](https://github.com/phalcon/crest/issues/1)
- Added `list` (aliases `commands`, `enumerate`), listing the available commands. [#1](https://github.com/phalcon/crest/issues/1)
- Added the `bootstrap` key to `crest.php`, naming the project front controller so commands that need a running application can boot one: `'bootstrap' => App\Front\AppFront::class`. Requires a `boot()` returning a container. [#1](https://github.com/phalcon/crest/issues/1)
- Added `Crest\Console\Input::argumentString()`, `optionString()` and `optionStringOrNull()`, narrowing the common string case so commands do not each repeat a type guard.
- Added `make:command`, generating a crest command for a package that contributes its own. Prints the `extra.crest.commands` block to declare it with, since that is the only way the registry finds a command. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:middleware`, generating an ADR middleware and printing the router middleware-map entry that activates it. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:provider`, generating a service provider for `Phalcon\Container` and printing the `registerProviders()` override that calls it. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:responder`, generating an ADR responder that implements the `Responder` contract directly. [#5](https://github.com/phalcon/crest/issues/5)
- Added `stub:publish`, copying packaged stubs into `resources/stubs/<flavor>/` so a project can edit them. The override chain already worked; nothing made it discoverable. [#5](https://github.com/phalcon/crest/issues/5)
- Added `--stub` to `make:action`, rendering any named stub instead of the `--responder` default. Passing both is rejected rather than silently resolved. [#5](https://github.com/phalcon/crest/issues/5)
- Added `command`, `middleware`, `provider` and `responder` to the default `paths` in `crest.php`, alongside `action`. Each is overridable per project as before. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Command\ProjectCommand`, the base for commands that read the project being run against. Contributed commands can extend it for `--directory` and `--config` handling instead of resolving those options themselves. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Generator\ClassName::suffixed()`, which appends an artifact suffix idempotently, so `make:middleware Cors` and `make:middleware CorsMiddleware` both produce `CorsMiddleware`. [#5](https://github.com/phalcon/crest/issues/5)

### Changed

- `make:action` now writes a `params()` declaration for routes with attributes, so they arrive constrained and cast rather than as raw strings.
- `make:action` now rejects a static segment after a placeholder and suggests the supported spelling: `/album/{id}/edit` is reported as `/album/edit/{id}`. Arguments trail the static path, so the first form has no class name that describes it.
- Renamed `Crest\Adr` to `Crest\ADR`, and `Flavor::Adr`, `Flavor::Cli` and `Flavor::Mvc` to `Flavor::ADR`, `Flavor::CLI` and `Flavor::MVC`, matching `Phalcon\ADR`. Backed values are unchanged.
- Renamed `Crest\ADR\CandidateSource` to `ActionResolver` and `PhalconRouterCandidates` to `PhalconRouterResolver`. One path now names exactly one Action, so there are no candidates to choose between.
- Dependencies now resolve against the PHP 8.1 floor via `config.platform`, so the lock matches the declared minimum.
- Default `paths` are now per flavor rather than shared. Only `adr` is populated, so a `cli` or `mvc` project is no longer offered directories for artifacts it has no command to generate. [#5](https://github.com/phalcon/crest/issues/5)
- `crest`, `crest list` and `crest --version` now open with a chevron mark before the name and version. Only the colour is dropped from piped output and when `NO_COLOR` is set; the glyph stays. [#5](https://github.com/phalcon/crest/issues/5)

### Fixed

- `make:middleware`, `make:provider` and `make:responder` no longer generate a class that cannot be parsed when the name given is already the suffix. `make:middleware Middleware` produced `final class Middleware implements Middleware` beside `use ...\Middleware;`. The contract is now imported under an alias. [#5](https://github.com/phalcon/crest/issues/5)
- Generators now fail instead of reporting a file they did not write. A target that could not be created produced two PHP warnings, `Created <file>` and exit 0; it now reports `could not create <directory>` and exits 1. [#5](https://github.com/phalcon/crest/issues/5)
- `stub:publish` now rejects a name that is a path. `stub:publish ../../elsewhere/thing` resolved and copied a file from outside the package. [#5](https://github.com/phalcon/crest/issues/5)
- `ClassName::suffixed()` now accepts non-Latin class names, matching PHP's own rule for an identifier. [#5](https://github.com/phalcon/crest/issues/5)

### Removed

- Removed the shadowed-action warning from `make:action`. One path names exactly one Action, so nothing can be shadowed.
- Removed the `phalcon/cli-options-parser` requirement. Crest never linked against it: the schema-aware definition layer stays in `Crest\Console\Parsing`, since `Cop\Parser` is schema-less by design.

[keep_a_changelog]: https://keepachangelog.com/en/1.0.0/
[semantic_versioning]: https://semver.org/spec/v2.0.0.html
[Unreleased]: https://github.com/phalcon/crest/commits/master
