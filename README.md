<p>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="art/logo-white.svg">
        <img src="art/logo.svg" alt="Kokonut" width="240" align="left">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="art/open-source-white.svg">
        <img src="art/open-source.svg" alt="Open source, MIT licensed" width="150" align="right">
    </picture>
</p>

<br clear="both">

---

# Swiss Company Registry

<p >
    <a href="https://packagist.org/packages/kokonut-ch/laravel-swiss-company-registry"><img src="https://img.shields.io/packagist/v/kokonut-ch/laravel-swiss-company-registry.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/kokonut-ch/laravel-swiss-company-registry"><img src="https://img.shields.io/packagist/php-v/kokonut-ch/laravel-swiss-company-registry.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://github.com/Kokonut-ch/SwissCompanyRegistry/actions"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/Kokonut-ch/SwissCompanyRegistry/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/kokonut-ch/laravel-swiss-company-registry"><img src="https://img.shields.io/packagist/dt/kokonut-ch/laravel-swiss-company-registry.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Search and validate Swiss companies through the two official registries, behind one facade and interchangeable providers:

- **[Zefix](https://www.zefix.admin.ch)** (Central Business Name Index, REST): every entity recorded in the cantonal commercial registers, with purpose, capital and excerpt links.
- **[UID register](https://www.uid.admin.ch)** (Federal Statistical Office, SOAP): every holder of a Swiss enterprise identification number, including entities outside the commercial register, plus the authoritative `ValidateUID` and `ValidateVatNumber` operations.

```php
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;

SwissCompany::search('Boulangerie Muster');      // company search across the registries
// or SwissCompany::suggest('muster');           // autocomplete alternative: matches anywhere
    
SwissCompany::find('CHE-123.456.788');           // full record: legal form, address, canton
SwissCompany::validateVatId('CHE-123.456.788');  // Active | Inactive | Unknown

// ---------------- 

use Kokonut\SwissCompanyRegistry\Facades\SwissUid;

SwissUid::parse('che 123 456 788')->formatVat();  // "CHE-123.456.788 TVA", offline
```
---

## Why this package

- **Interchangeable providers**: Zefix and the UID register behind one API, with automatic capability routing and fallback to the next provider on outage.
- **Fuzzy suggestions**: `suggest('muster')` finds "Boulangerie Muster", matching anywhere in the name instead of only from the start.
- **Offline UID validation**: the eCH-0097 check digit is verified locally, with no network call.
- **Tri-state online validation**: `Valid` / `Invalid` / `Unknown` (and `Active` / `Inactive` / `Unknown` for VAT), so an unreachable registry is never mistaken for a rejection.
- **Typed DTOs and quadrilingual enums**: `Company`, `Address`, `Canton` and `LegalForm`, with labels in German, French, Italian and English.
- **Response caching**: successful registry responses are cached to spare the public webservices.
- **Validation rules**: drop-in Laravel rules for forms, with strict variants for when a register is unreachable.
- **Testing fake and console commands**: `SwissCompany::fake()`, a fluent `CompanyFactory`, and two artisan commands to verify credentials from a new environment.

---

## Requirements

- PHP 8.3+
- Laravel 12 or 13

---

## Installation

```bash
composer require kokonut-ch/laravel-swiss-company-registry
php artisan vendor:publish --tag=swiss-company-registry-config
```

The UID register Public Services need no credentials. Zefix requires free API credentials from the Federal Office of Justice (see [docs/configuration.md](docs/configuration.md) for how to request them):

```env
ZEFIX_USERNAME=your-username
ZEFIX_PASSWORD=your-password
```

To run without Zefix credentials, set `SWISS_COMPANY_REGISTRY_PROVIDER=uid-register`; searches are then served by the UID register.

---

## Search

`search()` matches from the beginning of the company name, exactly like the registries do. Narrow it with the `SearchQuery` filters:

```php
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;

$results = SwissCompany::search('Boulangerie Muster');

$results = SwissCompany::search(
    SearchQuery::make('muster')->fuzzy()->canton('VD')->limit(10),
);

foreach ($results as $company) {
    $company->uid->format();            // "CHE-123.456.788"
    $company->name;                     // "Boulangerie Muster"
    $company->legalForm?->label('fr');  // "Entreprise individuelle"
}
```

### Suggest

`suggest()` is the autocomplete variant of `search()`: it matches anywhere in the name, so typing "muster" also finds "Boulangerie Muster":

```php
SwissCompany::suggest('muster', limit: 10, canton: 'VD');

// Ready-made options for a select input, keyed by formatted UID
SwissCompany::suggest('muster')->toSelectOptions();
// ["CHE-123.456.788" => "Boulangerie Muster (Lausanne)", ...]
```

---

## Find and validate

```php
use Kokonut\SwissCompanyRegistry\Rules\ValidUid;

$company = SwissCompany::find('CHE-123.456.788');
$company?->address?->oneLine();  // "Rue du Marché 12, 1003 Lausanne"
$company?->address?->block();    // same, one line per row, ready for a postal label

SwissCompany::validateUid('CHE-123.456.788');    // Valid | Invalid | Unknown
SwissCompany::validateVatId('CHE-123.456.788');  // Active | Inactive | Unknown

$request->validate([
    'uid' => ['required', new ValidUid],  // offline: format + check digit
]);
```

---

## The SwissUid facade

Parsing, formatting and check-digit validation work offline, without any registry call:

```php
use Kokonut\SwissCompanyRegistry\Facades\SwissUid;
use Kokonut\SwissCompanyRegistry\Enums\VatSuffix;

SwissUid::isValid('CHE-123.456.788');           // format + eCH-0097 check digit
$uid = SwissUid::parse('che 123 456 788 tva');  // lenient about separators and suffixes
$uid->format();                                 // "CHE-123.456.788"
SwissUid::formatVat($uid, VatSuffix::MWST);     // "CHE-123.456.788 MWST"
```

Every method returns or accepts the underlying `Kokonut\SwissCompanyRegistry\Values\Uid` value object, which is also what `Company->uid` holds.

---

## Providers at a glance

| Capability | Zefix | UID register |
| --- | --- | --- |
| Search by name, canton, legal form | ✅ | ✅ |
| Search by town / zip code | ❌ | ✅ |
| Search by commune (BFS id) | ✅ | ✅ |
| Search by registry office | ✅ | ❌ |
| Entities outside the commercial register | ❌ | ✅ |
| Full record (purpose, capital, excerpt links) | ✅ | ❌ |
| VAT registration details | ❌ | ✅ |
| `ValidateUID` / `ValidateVatNumber` | ❌ | ✅ |

### Switching providers

Zefix answers first by default. Change the default per environment, or force a provider per call:

```env
SWISS_COMPANY_REGISTRY_PROVIDER=uid-register
```

```php
SwissCompany::provider('uid-register')->search(SearchQuery::make('muster')->town('Lausanne'));
```

You rarely need to: when the preferred provider lacks a capability (VAT validation on Zefix), cannot honor a filter (town search on Zefix), or is down, the call automatically falls back to the next capable provider. Details in [docs/providers.md](docs/providers.md).

`SWISS_COMPANY_REGISTRY_ENVIRONMENT=test` targets both registries' integration systems instead of production, details in [docs/configuration.md](docs/configuration.md).

---

## Documentation

| Page | Covers |
| --- | --- |
| [docs/configuration.md](docs/configuration.md) | Environment variables, config file, Zefix credentials, UID register test environment |
| [docs/searching.md](docs/searching.md) | `search()` vs `suggest()`, the full `SearchQuery` API, provider routing, `SearchResults` |
| [docs/company-data.md](docs/company-data.md) | `Company`, `Address` (including its line-by-line API), and the `Canton` / `LegalForm` / `CompanyStatus` enums |
| [docs/validation.md](docs/validation.md) | The `Uid` value object, tri-state validation results, the three validation rules |
| [docs/providers.md](docs/providers.md) | Capability matrix, fallback chain, forcing a provider, custom providers, exceptions |
| [docs/testing.md](docs/testing.md) | The testing fake, `CompanyFactory`, assertions, and the artisan commands |

[docs/README.md](docs/README.md) indexes all of the above, plus the roadmap.

---

## Development

```bash
composer test   # phpstan + pint + type coverage + pest
composer lint   # auto-fix code style
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

---

## Contributing

Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

---

## Security

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

---

## Credits

### How this package was built

The providers, the value objects, the test suite and this documentation were written with
[Claude Code](https://claude.com/claude-code). During development every integration
decision was checked against the live registries: the Zefix REST endpoints and the UID
register SOAP operations were probed for real, search modes, XML namespaces and fault
shapes included, and the test fixtures mirror responses captured from those probes.

Everything was then reviewed by the Kokonut team before release. That review covers the
code and the interface mapping, not the registry data itself: what the registries answer
is what you get.

### Reference

The data and the interface contracts come from the two official Swiss registries: the
publicly documented [Zefix](https://www.zefix.admin.ch) REST API (Federal Office of
Justice) and the [UID register](https://www.uid.admin.ch) SOAP Public Services (Federal
Statistical Office), including the eCH-0097 check-digit algorithm. No source code was
copied from either administration; see [NOTICE](NOTICE).

This package is an independent client for those two registries. It is not affiliated
with, nor endorsed by, the Swiss federal administration, Zefix or the UID register.

### Authors

- [Kokonut](https://kokonut.ch)
- [All Contributors](../../contributors)

---

## License

Swiss Company Registry is open-sourced software licensed under the [MIT license](LICENSE.md).
