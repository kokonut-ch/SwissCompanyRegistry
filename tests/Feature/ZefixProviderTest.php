<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Exceptions\ConfigurationException;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Exceptions\TooManyResultsException;
use Kokonut\SwissCompanyRegistry\Exceptions\UnexpectedResponseException;
use Kokonut\SwissCompanyRegistry\Providers\ZefixProvider;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Tests\Fixtures\ZefixFixtures;
use Kokonut\SwissCompanyRegistry\Values\Uid;

function zefixProvider(): ZefixProvider
{
    return new ZefixProvider(
        ['username' => 'user', 'password' => 'secret'],
        ['retries' => 0],
    );
}

it('maps search hits to company summaries', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    $results = zefixProvider()->search(SearchQuery::make('aubry'));

    expect($results)->toHaveCount(2)
        ->and($results->provider)->toBe('zefix');

    $bakery = $results->all()[1];

    expect($bakery->uid->format())->toBe('CHE-107.185.562')
        ->and($bakery->name)->toBe('Boulangerie Aubry')
        ->and($bakery->legalSeat)->toBe('Delémont')
        ->and($bakery->legalForm)->toBe(LegalForm::SoleProprietorship)
        ->and($bakery->status)->toBe(CompanyStatus::Active)
        ->and($bakery->chId)->toBe('CH67010028221')
        ->and($bakery->ehraId)->toBe(677863)
        ->and($bakery->isActive())->toBeTrue();
});

it('sends wildcards, canton and legal form filters', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response([])]);

    zefixProvider()->search(
        SearchQuery::make('aubry')->fuzzy()->canton('JU')->legalForm(LegalForm::SoleProprietorship),
    );

    Http::assertSent(function (Request $request): bool {
        return $request['name'] === '*aubry*'
            && $request['canton'] === 'JU'
            && $request['legalFormUid'] === '0101'
            && $request['activeOnly'] === true;
    });
});

it('applies the query limit client-side', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    expect(zefixProvider()->search(SearchQuery::make('aubry')->limit(1)))->toHaveCount(1);
});

it('reports maintenance windows as unavailable', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(null, 503)]);

    zefixProvider()->search(SearchQuery::make('aubry'));
})->throws(RegistryUnavailableException::class);

it('reports oversized result lists as a dedicated exception', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(
        ['error' => ['type' => 'RESULTLIST_TO_LARGE', 'message' => 'too many results']],
        400,
    )]);

    zefixProvider()->search(SearchQuery::make('aaa'));
})->throws(TooManyResultsException::class);

it('reports other errors as unexpected responses', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(['error' => ['message' => 'nope']], 400)]);

    zefixProvider()->search(SearchQuery::make('aubry'));
})->throws(UnexpectedResponseException::class);

it('reports rejected credentials as a configuration error', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(null, 401)]);

    zefixProvider()->search(SearchQuery::make('aubry'));
})->throws(ConfigurationException::class, 'credentials');

it('maps the full company record', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::companyDetail())]);

    $company = zefixProvider()->find(Uid::parse('CHE-107.185.562'));

    expect($company)->not->toBeNull()
        ->and($company->name)->toBe('Boulangerie Aubry')
        ->and($company->canton)->toBe(Canton::JU)
        ->and($company->legalForm)->toBe(LegalForm::SoleProprietorship)
        ->and($company->purpose)->toBe("Exploitation d'une boulangerie-pâtisserie")
        ->and($company->address?->oneLine())->toBe('Rue Pierre Péquignat 8, 2800 Delémont')
        ->and($company->address?->careOf)->toBe('p.a. Famille Aubry')
        ->and($company->address?->lines())->toBe(['p.a. Famille Aubry', 'Rue Pierre Péquignat 8', '2800 Delémont'])
        ->and($company->inCommercialRegister)->toBeTrue()
        ->and($company->vatRegistered)->toBeNull()
        ->and($company->cantonalExcerptUrl)->toContain('ju.chregister.ch')
        ->and($company->zefixUrl)->toContain('zefix.admin.ch');
});

it('picks the Zefix detail link for the configured display locale', function (): void {
    config()->set('swiss-company-registry.locale', 'fr');

    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::companyDetail())]);

    $company = zefixProvider()->find(Uid::parse('CHE-107.185.562'));

    expect($company?->zefixUrl)->toBe('https://www.zefix.admin.ch/fr/search/entity/list?name=CHE107185562&directLink=true');
});

it('returns null when the UID is unknown', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response([])]);

    expect(zefixProvider()->find(Uid::parse('CHE-999.999.997')))->toBeNull();
});

it('cannot honor town or short-term queries', function (): void {
    $provider = zefixProvider();

    expect($provider->supports(SearchQuery::make('aubry')))->toBeTrue()
        ->and($provider->supports(SearchQuery::make('aubry')->town('Delémont')))->toBeFalse()
        ->and($provider->supports(SearchQuery::make('au')))->toBeFalse();
});

it('requires credentials', function (): void {
    $provider = new ZefixProvider([], []);

    $provider->search(SearchQuery::make('aubry'));
})->throws(ConfigurationException::class);

it('targets the integration system when the environment is test', function (): void {
    Http::fake(['www.zefixintg.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    $provider = new ZefixProvider(
        ['username' => 'user', 'password' => 'secret', 'environment' => 'test'],
        ['retries' => 0],
    );

    $provider->search(SearchQuery::make('aubry'));

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.zefixintg.admin.ch/'));
});

it('lets an explicit base_url win over the environment', function (): void {
    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::searchResults())]);

    $provider = new ZefixProvider(
        ['username' => 'user', 'password' => 'secret', 'environment' => 'test', 'base_url' => 'https://www.zefix.admin.ch/ZefixPublicREST/api/v1'],
        ['retries' => 0],
    );

    $provider->search(SearchQuery::make('aubry'));

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.zefix.admin.ch/'));
});

it('rejects an unknown environment', function (): void {
    $provider = new ZefixProvider(
        ['username' => 'user', 'password' => 'secret', 'environment' => 'staging'],
        ['retries' => 0],
    );

    $provider->search(SearchQuery::make('aubry'));
})->throws(ConfigurationException::class, 'The Zefix environment must be "production" or "test".');
