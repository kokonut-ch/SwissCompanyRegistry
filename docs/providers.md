# Providers and errors

The capability matrix, fallback chain, forcing a provider, custom providers, and the full exception table.

## Capability matrix

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

See [docs/searching.md](searching.md#provider-auto-routing) for how search filters map onto this matrix.

## Fallback chain

Each call is routed to the first provider in the configured chain (the `default` provider first, see [docs/configuration.md](configuration.md#default-and-fallback)) that:

1. Implements the capability contract the call needs (`SearchesCompanies`, `FindsCompanies`, `ValidatesUid`, `ValidatesVat`), and
2. For searches, whose `supports()` accepts the exact combination of filters in use.

On a `RegistryUnavailableException` or an `UnexpectedResponseException`, the next capable provider in the chain is tried, and a warning is logged with the provider name, the operation and the exception message. When `fallback` is disabled, only the default provider is ever consulted, so an outage propagates immediately instead of being retried elsewhere.

`TooManyResultsException` and `InvalidSearchQueryException` never trigger a fallback: they mean the query itself needs narrowing or fixing, which the next provider cannot help with, so they propagate immediately to the caller. `ConfigurationException` never triggers a fallback either, by design: missing or rejected Zefix credentials, or an invalid `environment` value, are configuration problems, and configuration problems must stay loud rather than being silently masked by whichever provider happens to work. A Zefix provider without credentials therefore does not get skipped in favor of another provider; set `SWISS_COMPANY_REGISTRY_PROVIDER=uid-register` if you want to run without Zefix credentials at all (see [docs/configuration.md](configuration.md#zefix-credentials)).

## Forcing a provider

Bypass routing entirely and call a specific provider directly:

```php
use Kokonut\SwissCompanyRegistry\Providers\UidRegisterProvider;

/** @var UidRegisterProvider $provider */
$provider = SwissCompany::provider('uid-register');
$provider->search(SearchQuery::make('muster')->town('Lausanne'));
```

`SwissCompany::provider(?string $name = null)` resolves (and memoizes) a provider by its config key; omitting `$name` resolves the default provider.

## Custom providers

```php
use Kokonut\SwissCompanyRegistry\Contracts\SearchesCompanies;

SwissCompany::extend('my-registry', function (array $config, array $http): SearchesCompanies {
    return new MyRegistryProvider($config, $http);
});
```

The creator closure receives the provider's own config array (`config('swiss-company-registry.providers.my-registry')`) and the shared HTTP options (`config('swiss-company-registry.http')`).

Add `my-registry` to `config('swiss-company-registry.providers')` (and optionally make it the `default`) and it participates in routing exactly like the built-in providers. Implement only the capability contracts your source can actually serve:

| Contract | Method | Used by |
| --- | --- | --- |
| `RegistryProvider` | `name(): string` | Every provider (base contract) |
| `SearchesCompanies` | `search(SearchQuery $query): SearchResults`, `supports(SearchQuery $query): bool` | `search()`, `suggest()` |
| `FindsCompanies` | `find(Uid $uid): ?Company` | `find()` |
| `ValidatesUid` | `validateUid(Uid $uid): UidValidationResult` | `validateUid()` |
| `ValidatesVat` | `validateVatId(Uid $uid): VatValidationResult` | `validateVatId()` |

## Errors

All exceptions extend `SwissCompanyRegistryException`:

| Exception | Meaning |
| --- | --- |
| `RegistryUnavailableException` | Maintenance window, network failure or rate limiting (including HTTP 429 from either registry); retry later |
| `TooManyResultsException` | The term matches too many companies; narrow the search |
| `InvalidSearchQueryException` | Filter combination no provider can honor, or rejected parameters |
| `InvalidUidException` | `Uid::parse()` received something that is not a UID |
| `ConfigurationException` | Missing or rejected credentials (HTTP 401/403 from Zefix), an invalid environment value, or an unknown provider name |
| `UnsupportedCapabilityException` | No configured provider offers the capability |
| `UnexpectedResponseException` | The registry answered something unexpected |

The validation methods (`validateUid()`, `validateVatId()`) never throw on outages; they return `Unknown` instead (see [docs/validation.md](validation.md#online-validation-tri-state-results)).
