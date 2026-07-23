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
| `SWISS_COMPANY_REGISTRY_LOCALE` | application locale | Display language for labels, VAT suffixes and Zefix links (`de`, `fr`, `it` or `en`) |
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

### `default`

`default` is the provider consulted first for every call (`zefix` or `uid-register`, or any custom provider registered through `extend()`, see [docs/providers.md](providers.md)). Every other configured provider follows it in the chain, so a call is automatically routed further along the chain when the default does not offer the capability (e.g. VAT validation on Zefix) or cannot honor the query filters (e.g. town search on Zefix). See [docs/providers.md](providers.md) for the full routing behavior.

### `environment`

`environment` is the global production/test switch: `test` targets both registries' integration systems (Zefix `zefixintg`, UID register `uid-wse-a`) instead of the live production endpoints. See [Test environment](#test-environment) below.

### Display language

```php
'locale' => env('SWISS_COMPANY_REGISTRY_LOCALE'),
```

`locale` is the display language used, by default, for:

- Enum labels: `Canton::label()`, `LegalForm::label()` and `LegalForm::shortLabel()`.
- The default VAT suffix: `VatSuffix::forLocale()` and, through it, `Uid::formatVat()` / `SwissUid::formatVat()`.
- The Zefix detail link picked in `Company->zefixUrl`.

Accepts `de`, `fr`, `it` or `en`. Left `null` (the default), it follows the application locale (`app()->getLocale()`).

An explicit locale argument passed to any of the methods above always wins over this setting, which in turn always wins over the application locale. The registry data itself is language-neutral: the UID register's public SOAP interface has no response-language parameter, and Zefix returns every language variant it has (see `zefixDetailWeb` in the raw payload). This setting only picks which of that already-present data to surface by default; it never changes what is fetched from the registries.

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

With the default configuration (`default` is `zefix`), search and find calls throw a `ConfigurationException` when Zefix has no credentials. This is by design: configuration problems stay loud instead of being silently swallowed by switching to another provider (see [docs/providers.md](providers.md#provider-routing)).

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
