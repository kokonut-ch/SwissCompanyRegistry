<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The registry consulted first for every call. Supported: "zefix",
    | "uid-register", plus any custom provider registered at runtime
    | through SwissCompany::extend().
    |
    */

    'default' => env('SWISS_COMPANY_REGISTRY_PROVIDER', 'zefix'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | "production" or "test". "test" targets the registries' integration
    | systems (Zefix zefixintg, UID register uid-wse-a) instead of the
    | live production endpoints. Each provider's own "environment" key,
    | when set, overrides this global switch. Note that the Zefix
    | integration system may require credentials issued specifically
    | for that system.
    |
    */

    'environment' => env('SWISS_COMPANY_REGISTRY_ENVIRONMENT', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Provider Fallback
    |--------------------------------------------------------------------------
    |
    | When enabled, a call is transparently routed to the next configured
    | provider whenever the preferred one does not offer the capability
    | (e.g. VAT validation on Zefix), cannot honor the query filters, or
    | is temporarily unavailable. When disabled, only the default
    | provider is ever consulted.
    |
    */

    'fallback' => (bool) env('SWISS_COMPANY_REGISTRY_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Response Cache
    |--------------------------------------------------------------------------
    |
    | Successful registry responses are cached to spare the public
    | webservices. "store" selects a cache store from your application's
    | cache config (null uses the default store). The TTL is expressed
    | in seconds. Failed lookups and "unknown" validation results are
    | never cached.
    |
    */

    'cache' => [
        'enabled' => (bool) env('SWISS_COMPANY_REGISTRY_CACHE', true),
        'store' => env('SWISS_COMPANY_REGISTRY_CACHE_STORE'),
        'ttl' => (int) env('SWISS_COMPANY_REGISTRY_CACHE_TTL', 21600),
        'prefix' => env('SWISS_COMPANY_REGISTRY_CACHE_PREFIX', 'swiss-company-registry'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | Timeouts are expressed in seconds, the retry delay in milliseconds.
    | Retries only apply to connection failures, never to HTTP error
    | responses.
    |
    */

    'http' => [
        'timeout' => (int) env('SWISS_COMPANY_REGISTRY_TIMEOUT', 10),
        'connect_timeout' => (int) env('SWISS_COMPANY_REGISTRY_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('SWISS_COMPANY_REGISTRY_RETRIES', 2),
        'retry_delay' => (int) env('SWISS_COMPANY_REGISTRY_RETRY_DELAY', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Zefix (Central Business Name Index) requires free credentials issued
    | on request by the Federal Office of Justice: https://www.zefix.admin.ch
    |
    | The UID register Public Services (Federal Statistical Office) need no
    | credentials.
    |
    | Both providers inherit the global "environment" switch above. Zefix
    | can be overridden with an explicit "base_url"; the UID register can
    | be overridden with its own "environment" or an explicit "endpoint".
    |
    */

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

];
