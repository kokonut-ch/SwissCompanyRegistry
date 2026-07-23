# Providers and errors

The capability matrix, provider routing, forcing a provider, custom providers, and the full exception table.

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

## Provider routing

The chain is the `default` provider first (see [docs/configuration.md](configuration.md#default)), then every other configured provider and every provider registered through `extend()`. The chain always contains every configured provider; it does not shrink or grow depending on provider health.

A call is served by the first provider in the chain that:

1. Implements the capability contract the call needs (`SearchesCompanies`, `FindsCompanies`, `ValidatesUid`, `ValidatesVat`), and
2. For searches, whose `supports()` accepts the exact combination of filters in use.

That is the only routing logic. Once that provider is picked, it is the one that serves the call, full stop: provider selection is never based on health, so its failures propagate straight to the caller instead of being retried elsewhere. Depending on what the provider does, the caller can expect:

- `RegistryUnavailableException`: maintenance window, network failure or rate limiting.
- `UnexpectedResponseException`: the registry answered something unexpected.
- `TooManyResultsException`: the term matches too many companies; narrow the search.
- `InvalidSearchQueryException`: the query itself needs narrowing or fixing.

Configuration errors are always loud, by design: missing or rejected Zefix credentials, an invalid `environment` value, or an unknown provider name raise a `ConfigurationException` instead of silently being masked by switching providers. A Zefix provider without credentials therefore does not get skipped in favor of another provider; set `SWISS_COMPANY_REGISTRY_PROVIDER=uid-register` if you want to run without Zefix credentials at all (see [docs/configuration.md](configuration.md#zefix-credentials)).

When no configured provider offers the required capability at all, the call throws `UnsupportedCapabilityException` before any request is made.

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
