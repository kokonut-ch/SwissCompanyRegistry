<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidSearchQueryException;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Providers\UidRegisterProvider;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Tests\Fixtures\UidRegisterFixtures;
use Kokonut\SwissCompanyRegistry\Values\Uid;

function uidRegisterProvider(): UidRegisterProvider
{
    return new UidRegisterProvider([], ['retries' => 0]);
}

it('validates a UID against the register', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::validateResult('ValidateUID', true))]);

    expect(uidRegisterProvider()->validateUid(Uid::parse('CHE-109.322.551')))
        ->toBe(UidValidationResult::Valid);

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<uid>CHE109322551</uid>')
        && $request->hasHeader('SOAPAction', '"http://www.uid.admin.ch/xmlns/uid-wse/IPublicServices/ValidateUID"'));
});

it('reports an unknown UID as invalid', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::validateResult('ValidateUID', false))]);

    expect(uidRegisterProvider()->validateUid(Uid::parse('CHE-109.322.551')))
        ->toBe(UidValidationResult::Invalid);
});

it('returns unknown when the register is down or unreadable', function (): void {
    Http::fake([
        'www.uid-wse.admin.ch/*' => Http::sequence()
            ->push(null, 503)
            ->push('<garbled'),
    ]);

    $provider = uidRegisterProvider();
    $uid = Uid::parse('CHE-109.322.551');

    expect($provider->validateUid($uid))->toBe(UidValidationResult::Unknown)
        ->and($provider->validateUid($uid))->toBe(UidValidationResult::Unknown);
});

it('validates a VAT number against the register', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::validateResult('ValidateVatNumber', true))]);

    expect(uidRegisterProvider()->validateVatId(Uid::parse('CHE-109.322.551')))
        ->toBe(VatValidationResult::Active);

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<vatNumber>CHE109322551</vatNumber>'));
});

it('maps SOAP search results to company summaries', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::searchResponse())]);

    $results = uidRegisterProvider()->search(SearchQuery::make('aubry')->fuzzy());

    expect($results)->toHaveCount(2)
        ->and($results->provider)->toBe('uid-register');

    $bakery = $results->all()[1];

    expect($bakery->uid->format())->toBe('CHE-107.185.562')
        ->and($bakery->name)->toBe('Boulangerie Aubry')
        ->and($bakery->legalSeat)->toBe('Delémont')
        ->and($bakery->canton)->toBe(Canton::JU)
        ->and($bakery->legalForm)->toBe(LegalForm::SoleProprietorship)
        ->and($bakery->status)->toBe(CompanyStatus::Active)
        ->and($bakery->chId)->toBe('CH67010028221')
        ->and($bakery->ehraId)->toBe(677863)
        ->and($bakery->rating)->toBe(97);

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<sh:searchMode>Fuzzy</sh:searchMode>')
        && str_contains($request->body(), '<organisationName>aubry</organisationName>'));
});

it('sends canton, town and status filters in the SOAP envelope', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::searchResponse())]);

    uidRegisterProvider()->search(SearchQuery::make('aubry')->town('Delémont')->canton('JU'));

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<town>Delémont</town>')
        && str_contains($request->body(), '<cantonAbbreviation>JU</cantonAbbreviation>')
        && str_contains($request->body(), '<uidregStatusEnterpriseDetail>3</uidregStatusEnterpriseDetail>'));
});

it('reports a raw HTTP 429 as rate limiting', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(null, 429)]);

    uidRegisterProvider()->search(SearchQuery::make('aubry'));
})->throws(RegistryUnavailableException::class);

it('translates data-validation faults into invalid-query exceptions', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(
        UidRegisterFixtures::businessFault('Data_validation_failed', 'No search parameters were specified.'),
        500,
    )]);

    uidRegisterProvider()->search(SearchQuery::make('aubry'));
})->throws(InvalidSearchQueryException::class, 'No search parameters were specified.');

it('maps the full company record including VAT information', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::getByUidResponse())]);

    $company = uidRegisterProvider()->find(Uid::parse('CHE-107.185.562'));

    expect($company)->not->toBeNull()
        ->and($company->name)->toBe('Boulangerie Aubry')
        ->and($company->canton)->toBe(Canton::JU)
        ->and($company->status)->toBe(CompanyStatus::Active)
        ->and($company->inCommercialRegister)->toBeTrue()
        ->and($company->vatRegistered)->toBeTrue()
        ->and($company->isVatRegistered())->toBeTrue()
        ->and($company->vatUid?->format())->toBe('CHE-107.185.562')
        ->and($company->address?->oneLine())->toBe('Rue Pierre Péquignat 8, 2800 Delémont')
        ->and($company->address?->careOf)->toBe('p.a. Famille Aubry');
});

it('returns null when the register has no entity for the UID', function (): void {
    Http::fake(['www.uid-wse.admin.ch/*' => Http::response(UidRegisterFixtures::emptyGetByUidResponse())]);

    expect(uidRegisterProvider()->find(Uid::parse('CHE-999.999.997')))->toBeNull();
});

it('targets the test system when configured', function (): void {
    Http::fake(['www.uid-wse-a.admin.ch/*' => Http::response(UidRegisterFixtures::validateResult('ValidateUID', true))]);

    $provider = new UidRegisterProvider(['environment' => 'test'], ['retries' => 0]);

    expect($provider->validateUid(Uid::parse('CHE-109.322.551')))->toBe(UidValidationResult::Valid);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.uid-wse-a.admin.ch/'));
});

it('supports every query except registry-office filters', function (): void {
    $provider = uidRegisterProvider();

    expect($provider->supports(SearchQuery::make('au')->town('Delémont')))->toBeTrue()
        ->and($provider->supports(SearchQuery::make('aubry')->registryOfCommerceId(670)))->toBeFalse();
});
