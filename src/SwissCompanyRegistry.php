<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Kokonut\SwissCompanyRegistry\Contracts\FindsCompanies;
use Kokonut\SwissCompanyRegistry\Contracts\RegistryProvider;
use Kokonut\SwissCompanyRegistry\Contracts\SearchesCompanies;
use Kokonut\SwissCompanyRegistry\Contracts\ValidatesUid;
use Kokonut\SwissCompanyRegistry\Contracts\ValidatesVat;
use Kokonut\SwissCompanyRegistry\Dto\Company;
use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\ConfigurationException;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidSearchQueryException;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidUidException;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Exceptions\UnexpectedResponseException;
use Kokonut\SwissCompanyRegistry\Exceptions\UnsupportedCapabilityException;
use Kokonut\SwissCompanyRegistry\Providers\UidRegisterProvider;
use Kokonut\SwissCompanyRegistry\Providers\ZefixProvider;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Search\SearchResults;
use Kokonut\SwissCompanyRegistry\Values\Uid;

/**
 * Routes each call to the first provider in the configured chain that
 * offers the required capability and can honor the query. When failover
 * is enabled (opt-in), an unavailable registry hands the call over to
 * the next eligible provider. Successful responses are cached when
 * caching is enabled.
 */
class SwissCompanyRegistry
{
    /**
     * Bump when the shape of cached DTOs changes, so stale serialized
     * objects from an older release are never unserialized into new
     * classes.
     */
    private const string CACHE_SCHEMA = 'v1';

    /** @var array<string, RegistryProvider> */
    protected array $resolved = [];

    /** @var array<string, Closure(array<string, mixed>, array<string, mixed>): RegistryProvider> */
    protected array $customCreators = [];

    /** @param array<string, mixed> $config */
    public function __construct(protected array $config = []) {}

    /**
     * Search companies by name, optionally narrowed with the SearchQuery
     * filters (canton, legal form, commune, ...).
     */
    public function search(SearchQuery|string $query): SearchResults
    {
        $query = is_string($query) ? SearchQuery::make($query) : $query;

        $candidates = $this->capable(SearchesCompanies::class);

        if ($candidates === []) {
            throw UnsupportedCapabilityException::for('company search');
        }

        $supporting = array_values(array_filter(
            $candidates,
            fn (SearchesCompanies $provider): bool => $provider->supports($query),
        ));

        if ($supporting === []) {
            throw new InvalidSearchQueryException('No available provider can honor this combination of search filters.');
        }

        return $this->attempt('search', $supporting, fn (SearchesCompanies $provider): SearchResults => $this->remember(
            'search:'.$provider->name().':'.$query->fingerprint(),
            fn (): SearchResults => $provider->search($query),
            cacheable: fn (SearchResults $results): bool => ! $results->isEmpty(),
        ));
    }

    /**
     * Autocomplete-style search: matches anywhere in the company name,
     * so "muster" also finds "Boulangerie Muster".
     */
    public function suggest(string $term, int $limit = 10, Canton|string|null $canton = null): SearchResults
    {
        return $this->search(
            SearchQuery::make($term)->fuzzy()->canton($canton)->limit($limit),
        )->take($limit);
    }

    /**
     * Full company record for a UID, or null when no registry knows it.
     *
     * @throws InvalidUidException
     */
    public function find(Uid|string $uid): ?Company
    {
        $uid = Uid::parse($uid);

        $candidates = $this->capable(FindsCompanies::class);

        if ($candidates === []) {
            throw UnsupportedCapabilityException::for('company lookup');
        }

        return $this->attempt('find', $candidates, fn (FindsCompanies $provider): ?Company => $this->remember(
            'find:'.$provider->name().':'.$uid->value,
            fn (): ?Company => $provider->find($uid),
            cacheable: fn (?Company $company): bool => $company !== null,
        ));
    }

    /**
     * Whether the UID exists in the register (also true for dissolved
     * companies). Unparseable input yields Invalid without any network
     * call; an unreachable registry yields Unknown, never an exception.
     */
    public function validateUid(Uid|string|null $uid): UidValidationResult
    {
        $uid = Uid::tryParse($uid);

        if ($uid === null) {
            return UidValidationResult::Invalid;
        }

        $candidates = $this->capable(ValidatesUid::class);

        if ($candidates === []) {
            throw UnsupportedCapabilityException::for('UID validation');
        }

        return $this->attempt(
            'validate-uid',
            $candidates,
            fn (ValidatesUid $provider): UidValidationResult => $this->remember(
                'validate-uid:'.$provider->name().':'.$uid->value,
                fn (): UidValidationResult => $provider->validateUid($uid),
                cacheable: fn (UidValidationResult $result): bool => $result->isKnown(),
            ),
            conclusive: fn (UidValidationResult $result): bool => $result->isKnown(),
        );
    }

    /**
     * Whether the number belongs to an active Swiss VAT registration.
     * Same semantics as validateUid() for bad input and outages.
     */
    public function validateVatId(Uid|string|null $uid): VatValidationResult
    {
        $uid = Uid::tryParse($uid);

        if ($uid === null) {
            return VatValidationResult::Inactive;
        }

        $candidates = $this->capable(ValidatesVat::class);

        if ($candidates === []) {
            throw UnsupportedCapabilityException::for('VAT validation');
        }

        return $this->attempt(
            'validate-vat',
            $candidates,
            fn (ValidatesVat $provider): VatValidationResult => $this->remember(
                'validate-vat:'.$provider->name().':'.$uid->value,
                fn (): VatValidationResult => $provider->validateVatId($uid),
                cacheable: fn (VatValidationResult $result): bool => $result->isKnown(),
            ),
            conclusive: fn (VatValidationResult $result): bool => $result->isKnown(),
        );
    }

    public function provider(?string $name = null): RegistryProvider
    {
        $name ??= $this->defaultProvider();

        return $this->resolved[$name] ??= $this->createProvider($name);
    }

    /**
     * Register a custom provider. The creator receives the provider's
     * config array and the shared HTTP options.
     *
     * @param  Closure(array<string, mixed>, array<string, mixed>): RegistryProvider  $creator
     */
    public function extend(string $name, Closure $creator): static
    {
        $this->customCreators[$name] = $creator;

        unset($this->resolved[$name]);

        return $this;
    }

    /**
     * Serve a call with the first eligible provider and, when failover is
     * enabled, hand it over to the next one while a registry is
     * unavailable or answers unexpectedly (or, for validations, cannot
     * reach a verdict). TooManyResultsException and
     * InvalidSearchQueryException always propagate at once: the next
     * provider cannot fix the query. A ConfigurationException from the
     * first provider propagates too, so configuration problems stay loud;
     * a misconfigured backup is skipped with a warning so that it never
     * masks the original outage, which is rethrown when no provider could
     * serve the call.
     *
     * @template TProvider of RegistryProvider
     * @template TValue
     *
     * @param  non-empty-list<TProvider>  $providers
     * @param  Closure(TProvider): TValue  $call
     * @param  (Closure(TValue): bool)|null  $conclusive
     * @return TValue
     */
    protected function attempt(string $operation, array $providers, Closure $call, ?Closure $conclusive = null): mixed
    {
        if (! $this->failoverEnabled()) {
            return $call($providers[0]);
        }

        $outage = null;
        $result = null;
        $hasResult = false;

        foreach ($providers as $provider) {
            try {
                $result = $call($provider);
            } catch (RegistryUnavailableException|UnexpectedResponseException $exception) {
                Log::warning('Swiss company registry provider failed, failing over to the next one.', [
                    'provider' => $provider->name(),
                    'operation' => $operation,
                    'message' => $exception->getMessage(),
                ]);

                $outage ??= $exception;

                continue;
            } catch (ConfigurationException $exception) {
                if ($outage === null && ! $hasResult) {
                    throw $exception;
                }

                Log::warning('Swiss company registry backup provider is misconfigured, skipping it.', [
                    'provider' => $provider->name(),
                    'operation' => $operation,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($conclusive === null || $conclusive($result)) {
                return $result;
            }

            $hasResult = true;
        }

        if ($hasResult) {
            return $result;
        }

        throw $outage ?? new RegistryUnavailableException($providers[0]->name(), "No provider could serve the {$operation} call.");
    }

    protected function failoverEnabled(): bool
    {
        return (bool) ($this->config['failover'] ?? false);
    }

    protected function defaultProvider(): string
    {
        $default = $this->config['default'] ?? 'zefix';

        return is_string($default) ? $default : 'zefix';
    }

    /**
     * Provider chain in routing order: the default first, then every
     * other configured or custom provider.
     *
     * @return list<RegistryProvider>
     */
    protected function chain(): array
    {
        $names = [$this->defaultProvider()];

        $configured = $this->config['providers'] ?? [];

        foreach ([...array_keys(is_array($configured) ? $configured : []), ...array_keys($this->customCreators)] as $name) {
            if (! in_array($name, $names, true)) {
                $names[] = (string) $name;
            }
        }

        return array_map(fn (string $name): RegistryProvider => $this->provider($name), $names);
    }

    /**
     * @template TContract of RegistryProvider
     *
     * @param  class-string<TContract>  $contract
     * @return list<TContract>
     */
    protected function capable(string $contract): array
    {
        return array_values(array_filter(
            $this->chain(),
            fn (RegistryProvider $provider): bool => $provider instanceof $contract,
        ));
    }

    protected function createProvider(string $name): RegistryProvider
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->providerConfig($name), $this->httpConfig());
        }

        return match ($name) {
            'zefix' => new ZefixProvider($this->providerConfig($name), $this->httpConfig()),
            'uid-register' => new UidRegisterProvider($this->providerConfig($name), $this->httpConfig()),
            default => throw new ConfigurationException("Unknown company registry provider \"{$name}\"."),
        };
    }

    /** @return array<string, mixed> */
    protected function providerConfig(string $name): array
    {
        $providers = $this->config['providers'] ?? [];
        $config = is_array($providers) ? ($providers[$name] ?? []) : [];
        $config = is_array($config) ? $config : [];

        if (! isset($config['environment'])) {
            $environment = $this->config['environment'] ?? 'production';
            $config['environment'] = is_string($environment) ? $environment : 'production';
        }

        return $config;
    }

    /** @return array<string, mixed> */
    protected function httpConfig(): array
    {
        $http = $this->config['http'] ?? [];

        return is_array($http) ? $http : [];
    }

    /**
     * Cache pass-through. Only values accepted by $cacheable are stored;
     * exceptions are never cached.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $resolve
     * @param  (Closure(TValue): bool)|null  $cacheable
     * @return TValue
     */
    protected function remember(string $key, Closure $resolve, ?Closure $cacheable = null): mixed
    {
        $cache = $this->config['cache'] ?? [];
        $cache = is_array($cache) ? $cache : [];

        if (! (bool) ($cache['enabled'] ?? true)) {
            return $resolve();
        }

        $store = $cache['store'] ?? null;
        $store = is_string($store) ? $store : null;
        $prefix = $cache['prefix'] ?? 'swiss-company-registry';
        $ttl = $cache['ttl'] ?? 21600;

        try {
            $repository = Cache::store($store);
        } catch (InvalidArgumentException) {
            throw new ConfigurationException('Unknown cache store "'.$store.'" in swiss-company-registry.cache.store.');
        }

        $key = (is_string($prefix) ? $prefix : 'swiss-company-registry').':'.self::CACHE_SCHEMA.':'.$key;

        $cached = $repository->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $resolve();

        if ($cacheable === null ? $value !== null : $cacheable($value)) {
            $repository->put($key, $value, is_numeric($ttl) ? (int) $ttl : 21600);
        }

        return $value;
    }
}
