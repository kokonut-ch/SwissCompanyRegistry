<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kokonut\SwissCompanyRegistry\Contracts\SearchesCompanies;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\ConfigurationException;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Exceptions\UnsupportedCapabilityException;
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Search\SearchResults;
use Kokonut\SwissCompanyRegistry\SwissCompanyRegistry;
use Kokonut\SwissCompanyRegistry\Tests\Fixtures\UidRegisterFixtures;
use Kokonut\SwissCompanyRegistry\Tests\Fixtures\ZefixFixtures;

/**
 * @param  array<string, mixed>  $overrides
 */
function registry(array $overrides = []): SwissCompanyRegistry
{
    return new SwissCompanyRegistry(array_replace_recursive([
        'default' => 'zefix',
        'fallback' => true,
        'cache' => ['enabled' => false],
        'http' => ['retries' => 0],
        'providers' => [
            'zefix' => ['username' => 'user', 'password' => 'secret'],
            'uid-register' => [],
        ],
    ], $overrides));
}

it('routes searches to the default provider', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    $results = registry()->search('aubry');

    expect($results->provider)->toBe('zefix')->and($results)->toHaveCount(2);
});

it('routes town-filtered searches to the UID register because Zefix cannot filter by town', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::searchResponse())]);

    $results = registry()->search(SearchQuery::make('aubry')->town('Delémont'));

    expect($results->provider)->toBe('uid-register');

    Http::assertSentCount(1);
});

it('falls back to the UID register when Zefix is under maintenance', function (): void {
    Http::fake([
        'www.zefix.admin.ch/*' => Http::response(null, 503),
        'www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::searchResponse()),
    ]);

    expect(registry()->search('aubry')->provider)->toBe('uid-register');
});

it('surfaces unavailability when every capable provider is down', function (): void {
    Http::fake([
        'www.zefix.admin.ch/*' => Http::response(null, 503),
        'www.uid-wse.admin.ch/*' => Http::response(null, 503),
    ]);

    registry()->search('aubry');
})->throws(RegistryUnavailableException::class);

it('falls back to the UID register when Zefix returns an unexpected response', function (): void {
    Http::fake([
        'www.zefix.admin.ch/*' => Http::response(['error' => ['message' => 'nope']], 400),
        'www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::searchResponse()),
    ]);

    expect(registry()->search('aubry')->provider)->toBe('uid-register');
});

it('never falls back for a Zefix provider missing credentials: it throws loudly instead', function (): void {
    Http::fake();

    expect(fn (): SearchResults => registry(['providers' => ['zefix' => ['username' => null, 'password' => null]]])->search('aubry'))
        ->toThrow(ConfigurationException::class);

    Http::assertNothingSent();
});

it('routes UID validation to the UID register even when Zefix is the default', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::validateResult('ValidateUID', true))]);

    expect(registry()->validateUid('CHE-109.322.551'))->toBe(UidValidationResult::Valid);
});

it('rejects capability calls when fallback is disabled and the default cannot serve them', function (): void {
    registry(['fallback' => false])->validateUid('CHE-109.322.551');
})->throws(UnsupportedCapabilityException::class);

it('short-circuits unparseable input without any network call', function (): void {
    Http::fake();

    expect(registry()->validateUid('not-a-uid'))->toBe(UidValidationResult::Invalid);

    Http::assertNothingSent();
});

it('caches identical searches', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    $registry = registry(['cache' => ['enabled' => true, 'prefix' => 'test-cache']]);

    $first = $registry->search('aubry');
    $second = $registry->search('aubry');

    expect($second)->toEqual($first);

    Http::assertSentCount(1);
});

it('does not cache an empty search result', function (): void {
    Http::fake([
        'www.zefix.admin.ch/*' => Http::sequence()
            ->push([])
            ->push(ZefixFixtures::searchResults()),
    ]);

    $registry = registry(['cache' => ['enabled' => true, 'prefix' => 'test-empty-search']]);

    $first = $registry->search('aubry');
    $second = $registry->search('aubry');

    expect($first->isEmpty())->toBeTrue()
        ->and($second->isEmpty())->toBeFalse();

    Http::assertSentCount(2);
});

it('reports an unknown cache store as a configuration error', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    registry(['cache' => ['enabled' => true, 'store' => 'does-not-exist']])->search('aubry');
})->throws(ConfigurationException::class);

it('does not cache unknown validation results', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(null, 503)]);

    $registry = registry(['cache' => ['enabled' => true, 'prefix' => 'test-unknown']]);

    expect($registry->validateUid('CHE-109.322.551'))->toBe(UidValidationResult::Unknown)
        ->and($registry->validateUid('CHE-109.322.551'))->toBe(UidValidationResult::Unknown);

    Http::assertSentCount(2);
});

it('suggests with wildcards and honors the limit', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    $results = registry()->suggest('aubry', limit: 1, canton: 'JU');

    expect($results)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => $request['name'] === '*aubry*' && $request['canton'] === 'JU');
});

it('supports custom providers through extend', function (): void {
    $registry = registry(['default' => 'memory'])->extend('memory', fn (): SearchesCompanies => new class implements SearchesCompanies
    {
        public function name(): string
        {
            return 'memory';
        }

        public function search(SearchQuery $query): SearchResults
        {
            return new SearchResults([], $this->name());
        }

        public function supports(SearchQuery $query): bool
        {
            return true;
        }
    });

    expect($registry->search('anything')->provider)->toBe('memory');
});

it('resolves through the facade and container', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    expect(SwissCompany::search('aubry'))->toHaveCount(2);
});

it('routes both registries to their integration systems when the global environment is test', function (): void {
    Http::fake([
        'www.zefixintg.admin.ch/*' => Http::response(ZefixFixtures::searchResults()),
        'www.uid-wse-a.admin.ch/*' => Http::response(UidRegisterFixtures::validateResult('ValidateUID', true)),
    ]);

    $registry = registry(['environment' => 'test']);

    expect($registry->search('aubry')->provider)->toBe('zefix');
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.zefixintg.admin.ch/'));

    expect($registry->validateUid('CHE-109.322.551'))->toBe(UidValidationResult::Valid);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.uid-wse-a.admin.ch/'));
});

it('lets a per-provider environment override the global environment', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::validateResult('ValidateUID', true))]);

    $registry = registry([
        'environment' => 'test',
        'providers' => ['uid-register' => ['environment' => 'production']],
    ]);

    expect($registry->validateUid('CHE-109.322.551'))->toBe(UidValidationResult::Valid);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.uid-wse.admin.ch/'));
});
