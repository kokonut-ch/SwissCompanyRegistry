# Documentation

Detailed guides for the Swiss Company Registry package. Start with the [main README](../README.md) for the pitch and a quick start.

| Page | Covers |
| --- | --- |
| [configuration.md](configuration.md) | Environment variables, config file, Zefix credentials, UID register test environment |
| [searching.md](searching.md) | `search()` vs `suggest()`, the full `SearchQuery` API, provider routing, `SearchResults` |
| [company-data.md](company-data.md) | `Company`, `Address` (including its line-by-line API), and the `Canton` / `LegalForm` / `CompanyStatus` enums |
| [validation.md](validation.md) | The `Uid` value object, tri-state validation results, the three validation rules |
| [providers.md](providers.md) | Capability matrix, provider routing, forcing a provider, custom providers, exceptions |
| [testing.md](testing.md) | The testing fake, `CompanyFactory`, assertions, and the artisan commands |

## Highlights

- **Interchangeable providers**: Zefix and the UID register behind one API, each call served by the first provider able to honor it (capabilities and filters).
- **Fuzzy suggestions**: `suggest('muster')` finds "Boulangerie Muster", matching anywhere in the name instead of only from the start.
- **Offline UID validation**: the eCH-0097 check digit is verified locally, with no network call.
- **Tri-state online validation**: `Valid` / `Invalid` / `Unknown` (and `Active` / `Inactive` / `Unknown` for VAT), so an unreachable registry is never mistaken for a rejection.
- **Typed DTOs and quadrilingual enums**: `Company`, `Address`, `Canton` and `LegalForm`, with labels in German, French, Italian and English following the configured display locale.
- **Response caching**: successful registry responses are cached to spare the public webservices.
- **Validation rules**: drop-in Laravel rules for forms, with strict variants for when a register is unreachable.
- **Testing fake and console commands**: `SwissCompany::fake()`, a fluent `CompanyFactory`, and two artisan commands to verify credentials from a new environment.

## Roadmap

- SOGC/FOSC publication feeds (Zefix `sogc` endpoints)
- Lookup by CH-ID and EHRAID
- Commune and registry-office directories (for building filter UIs)
- UID register Partner Services (authenticated: 200-result search, QuickSearch, InfoAbo change notifications)
- NOGA activity codes
