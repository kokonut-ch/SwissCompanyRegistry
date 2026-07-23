# Documentation

Detailed guides for the Swiss Company Registry package. Start with the [main README](../README.md) for the pitch and a quick start.

| Page | Covers |
| --- | --- |
| [configuration.md](configuration.md) | Environment variables, config file, Zefix credentials, UID register test environment |
| [searching.md](searching.md) | `search()` vs `suggest()`, the full `SearchQuery` API, provider routing, `SearchResults` |
| [company-data.md](company-data.md) | `Company`, `Address` (including its line-by-line API), and the `Canton` / `LegalForm` / `CompanyStatus` enums |
| [validation.md](validation.md) | The `Uid` value object, tri-state validation results, the three validation rules |
| [providers.md](providers.md) | Capability matrix, fallback chain, forcing a provider, custom providers, exceptions |
| [testing.md](testing.md) | The testing fake, `CompanyFactory`, assertions, and the artisan commands |

## Roadmap

- SOGC/FOSC publication feeds (Zefix `sogc` endpoints)
- Lookup by CH-ID and EHRAID
- Commune and registry-office directories (for building filter UIs)
- UID register Partner Services (authenticated: 200-result search, QuickSearch, InfoAbo change notifications)
- NOGA activity codes
