# Providers and errors

The capability matrix, provider routing and failover, forcing a provider, custom providers, and the full exception table.

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

When no configured provider offers the required capability at all, the call throws `UnsupportedCapabilityException` before any request is made.

## Failover

When `failover` is enabled (it is opt-in, see [docs/configuration.md](configuration.md#failover)), a call whose provider fails is handed over to the next eligible provider in the chain, the one that offers the same capability and honors the same filters:

| Outcome of a provider | Failover |
| --- | --- |
| `RegistryUnavailableException` (maintenance window, network failure, rate limiting) | ✅ next provider |
| `UnexpectedResponseException` (the registry answered something unexpected) | ✅ next provider |
| `Unknown` from `validateUid()` / `validateVatId()` (registry unreachable) | ✅ next provider |
| `TooManyResultsException` (the term matches too many companies) | ❌ propagates |
| `InvalidSearchQueryException` (the query needs narrowing or fixing) | ❌ propagates |
| `ConfigurationException` of the first eligible provider | ❌ propagates |

✅ the call is retried on the next provider, ❌ the exception reaches the caller immediately.

Every provider skipped logs a warning naming the provider, the operation and the exception message. When every eligible provider fails, the exception of the first one is rethrown, so a `RegistryUnavailableException` still reaches the caller as such.

A failed-over call may return a less complete record: a company found through the UID register has no `purpose` and no capital (see the capability matrix above). `SearchResults->provider` tells which registry answered a search.

With `failover` disabled (the default), the first eligible provider serves the call, full stop: its failures propagate straight to the caller.

Configuration errors are always loud, by design: missing or rejected Zefix credentials, an invalid `environment` value, or an unknown provider name raise a `ConfigurationException` instead of silently being masked by switching providers. A default Zefix provider without credentials therefore does not get skipped in favor of another provider; set `SWISS_COMPANY_REGISTRY_PROVIDER=uid-register` if you want to run without Zefix credentials at all (see [docs/configuration.md](configuration.md#zefix-credentials)). A misconfigured **backup**, on the other hand, is skipped with a warning during failover, so that it never hides the original outage.

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
