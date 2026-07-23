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

---

## Requirements

- PHP 8.3+
- Laravel 12 or 13

---

## Installation

```bash
composer require kokonut-ch/laravel-swiss-company-registry
```

The UID register needs no credentials. Zefix requires free API credentials from the Federal Office of Justice:

```env
ZEFIX_USERNAME=your-username
ZEFIX_PASSWORD=your-password
```

No credentials yet? Set `SWISS_COMPANY_REGISTRY_PROVIDER=uid-register` and every call is served by the UID register. Publishing the config file, caching, locales and the test environment are covered in [docs/configuration.md](docs/configuration.md).

---

## Usage

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

Every call is routed to the first provider that offers the capability and supports the query filters; provider failures surface as exceptions, never as a silent switch. Choosing the default provider, forcing one per call and the production/test switch are covered in [docs/providers.md](docs/providers.md) and [docs/configuration.md](docs/configuration.md).

---

## Documentation

| Page | Covers |
| --- | --- |
| [docs/configuration.md](docs/configuration.md) | Environment variables, config file, Zefix credentials, display language, test environment |
| [docs/searching.md](docs/searching.md) | `search()` vs `suggest()`, the full `SearchQuery` API, provider routing, `SearchResults` |
| [docs/company-data.md](docs/company-data.md) | `Company`, `Address` (including its line-by-line API), and the `Canton` / `LegalForm` / `CompanyStatus` enums |
| [docs/validation.md](docs/validation.md) | The `SwissUid` facade, the `Uid` value object, tri-state validation results, the three validation rules |
| [docs/providers.md](docs/providers.md) | Capability matrix, provider routing, forcing a provider, custom providers, exceptions |
| [docs/testing.md](docs/testing.md) | The testing fake, `CompanyFactory`, assertions, and the artisan commands |

[docs/README.md](docs/README.md) indexes all of the above, plus the feature highlights and the roadmap.

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
