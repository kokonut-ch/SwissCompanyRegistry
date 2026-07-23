# Configuration

Environment variables, the config file, Zefix credentials, and the test environment switch.

Publish the config file if you want to tweak defaults:

```bash
php artisan vendor:publish --tag=swiss-company-registry-config
```

This creates `config/swiss-company-registry.php`.

## Environment variables

| Variable | Default | Purpose |
| --- | --- | --- |
| `SWISS_COMPANY_REGISTRY_PROVIDER` | `zefix` | Provider consulted first (`zefix` or `uid-register`) |
| `SWISS_COMPANY_REGISTRY_ENVIRONMENT` | `production` | `test` targets both registries' integration systems |
| `SWISS_COMPANY_REGISTRY_FALLBACK` | `true` | Route to the next capable provider on outage or missing capability |
| `SWISS_COMPANY_REGISTRY_CACHE` | `true` | Cache successful registry responses |
| `SWISS_COMPANY_REGISTRY_CACHE_STORE` | app default | Cache store to use |
| `SWISS_COMPANY_REGISTRY_CACHE_TTL` | `21600` | Cache lifetime in seconds |
| `SWISS_COMPANY_REGISTRY_CACHE_PREFIX` | `swiss-company-registry` | Cache key prefix |
| `SWISS_COMPANY_REGISTRY_TIMEOUT` | `10` | HTTP timeout in seconds |
| `SWISS_COMPANY_REGISTRY_CONNECT_TIMEOUT` | `5` | HTTP connect timeout in seconds |
| `SWISS_COMPANY_REGISTRY_RETRIES` | `2` | Retries on connection failures |
| `SWISS_COMPANY_REGISTRY_RETRY_DELAY` | `200` | Delay between retries, in milliseconds |
| `ZEFIX_BASE_URL` | derived from environment | Explicit Zefix endpoint override, wins over the environment |
| `ZEFIX_USERNAME` | none | Zefix API username |
| `ZEFIX_PASSWORD` | none | Zefix API password |
| `UID_REGISTER_ENVIRONMENT` | inherits `SWISS_COMPANY_REGISTRY_ENVIRONMENT` | Per-provider override of the global environment |
| `UID_REGISTER_ENDPOINT` | derived from environment | Explicit SOAP endpoint override, wins over the environment |

Retries only apply to connection failures, never to HTTP error responses.

## Config file walkthrough

### `default` and `fallback`

`default` is the provider consulted first for every call (`zefix` or `uid-register`, or any custom provider registered through `extend()`, see [docs/providers.md](providers.md)).

`fallback` decides whether a call is transparently routed to the next configured provider when the preferred one does not offer the capability (e.g. VAT validation on Zefix), cannot honor the query filters, or is temporarily unavailable. When disabled, only the default provider is ever consulted. See [docs/providers.md](providers.md) for the full routing and fallback behavior.

### `environment`

`environment` is the global production/test switch: `test` targets both registries' integration systems (Zefix `zefixintg`, UID register `uid-wse-a`) instead of the live production endpoints. See [Test environment](#test-environment) below.

### `cache`

```php
'cache' => [
    'enabled' => (bool) env('SWISS_COMPANY_REGISTRY_CACHE', true),
    'store' => env('SWISS_COMPANY_REGISTRY_CACHE_STORE'),
    'ttl' => (int) env('SWISS_COMPANY_REGISTRY_CACHE_TTL', 21600),
    'prefix' => env('SWISS_COMPANY_REGISTRY_CACHE_PREFIX', 'swiss-company-registry'),
],
```

Successful registry responses are cached to spare the public webservices. `store` selects a cache store from your application's cache config (`null` uses the default store). The TTL is expressed in seconds. Failed lookups and "unknown" validation results are never cached.

### `http`

```php
'http' => [
    'timeout' => (int) env('SWISS_COMPANY_REGISTRY_TIMEOUT', 10),
    'connect_timeout' => (int) env('SWISS_COMPANY_REGISTRY_CONNECT_TIMEOUT', 5),
    'retries' => (int) env('SWISS_COMPANY_REGISTRY_RETRIES', 2),
    'retry_delay' => (int) env('SWISS_COMPANY_REGISTRY_RETRY_DELAY', 200),
],
```

Timeouts are expressed in seconds, the retry delay in milliseconds. These options are shared by every provider.

### `providers`

```php
'providers' => [
    'zefix' => [
        'base_url' => env('ZEFIX_BASE_URL'),
        'username' => env('ZEFIX_USERNAME'),
        'password' => env('ZEFIX_PASSWORD'),
    ],

    'uid-register' => [
        'environment' => env('UID_REGISTER_ENVIRONMENT'),
        'endpoint' => env('UID_REGISTER_ENDPOINT'),
    ],
],
```

Each key is a provider name; each value is that provider's own config array, passed straight to it. Custom providers registered through `extend()` read their config from the same array, keyed by the name given to `extend()`.

## Zefix credentials

Zefix (Central Business Name Index) requires free API credentials issued on request by the Federal Office of Justice. Request them through [zefix.admin.ch](https://www.zefix.admin.ch), then set:

```env
ZEFIX_USERNAME=your-username
ZEFIX_PASSWORD=your-password
```

Without credentials, calls routed to Zefix throw a `ConfigurationException` (see [docs/providers.md](providers.md#errors)).

### Running without Zefix credentials

With the default configuration (`default` is `zefix`), search and find calls throw a `ConfigurationException` when Zefix has no credentials. This is by design: configuration problems stay loud instead of being silently swallowed by falling back to another provider (see [docs/providers.md](providers.md#fallback-chain)).

UID and VAT validation (`validateUid()`, `validateVatId()`) and town/zip-filtered searches work without any Zefix credentials, because only the UID register offers those capabilities and the call is routed there directly.

To run entirely without Zefix credentials, set the UID register as the default provider:

```env
SWISS_COMPANY_REGISTRY_PROVIDER=uid-register
```

Every call is then served by the UID register, with fewer fields on the full company record (no `purpose`, no `capitalNominal` / `capitalCurrency`, those are Zefix-only).

## Test environment

One switch flips both registries to their integration systems, useful for exercising search and validation flows without touching live data:

```env
SWISS_COMPANY_REGISTRY_ENVIRONMENT=test
```

This routes Zefix to `zefixintg.admin.ch` and the UID register to `uid-wse-a.admin.ch`. The Zefix integration system may require its own set of credentials, issued separately from the production ones; request them the same way as production credentials, see [Zefix credentials](#zefix-credentials) above.

Per-provider overrides win over the global switch:

- `UID_REGISTER_ENVIRONMENT` overrides the environment for the UID register only, regardless of the global switch.
- `ZEFIX_BASE_URL` overrides the Zefix endpoint entirely, regardless of environment.
- `UID_REGISTER_ENDPOINT` overrides the UID register SOAP endpoint entirely, regardless of environment.

```env
SWISS_COMPANY_REGISTRY_ENVIRONMENT=test
UID_REGISTER_ENVIRONMENT=production
```
