# Swiss Company Registry

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `kokonut-ch/laravel-swiss-company-registry`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Architecture

`SwissCompanyRegistry` (the facade root, bound as a singleton) routes each call to the first provider in the configured chain that implements the required capability contract (`SearchesCompanies`, `FindsCompanies`, `ValidatesUid`, `ValidatesVat`) and whose `supports()` accepts the query. Provider selection is never based on health: a failing provider's exception propagates to the caller instead of triggering a fallback. Providers map registry payloads into the immutable DTOs (`Company`, `CompanySummary`, `Address`) and enums; nothing outside `src/Providers` touches raw registry payloads. `validateUid`/`validateVatId` never throw on outages; they return `Unknown`.

- DTOs are `final readonly` with constructor-promoted, documented properties.
- No new HTTP behavior without a matching `Http::fake()` test. Fixtures in `tests/Fixtures` mirror real captured responses; keep them realistic.
- The eCH-0097 check digit, legal form codes and canton abbreviations are normative; do not "fix" them without citing the standard.
- `composer test` must stay green: PHPStan level 7, Pint (Laravel preset), 100% type coverage, Pest.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
