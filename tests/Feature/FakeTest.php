<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Testing\CompanyFactory;

it('searches seeded companies with prefix and fuzzy semantics', function (): void {
    SwissCompany::fake([
        CompanyFactory::new('CHE-107.185.562')->name('Boulangerie Muster')->make(),
        CompanyFactory::new('CHE-109.322.551')->name('Muster Carrelage Sàrl')->canton('JU')->make(),
        CompanyFactory::new('CHE-123.456.788')->name('Kokonut SA')->canton('VD')->make(),
    ]);

    expect(SwissCompany::search('muster'))->toHaveCount(1)
        ->and(SwissCompany::suggest('muster'))->toHaveCount(2)
        ->and(SwissCompany::search(SearchQuery::make('muster')->fuzzy()->canton('JU')))->toHaveCount(1)
        ->and(SwissCompany::suggest('muster')->toSelectOptions())->toBe([
            'CHE-107.185.562' => 'Boulangerie Muster (Lausanne)',
            'CHE-109.322.551' => 'Muster Carrelage Sàrl (Lausanne)',
        ]);
});

it('filters out inactive companies unless asked otherwise', function (): void {
    SwissCompany::fake([
        CompanyFactory::new('CHE-107.185.562')->name('Muster SA')->cancelled()->make(),
    ]);

    expect(SwissCompany::search('muster'))->toHaveCount(0)
        ->and(SwissCompany::search(SearchQuery::make('muster')->includeInactive()))->toHaveCount(1);
});

it('finds and validates seeded companies', function (): void {
    $fake = SwissCompany::fake([
        CompanyFactory::new('CHE-107.185.562')->vatRegistered()->make(),
    ]);

    expect(SwissCompany::find('CHE-107.185.562')?->name)->toBe('Boulangerie Muster')
        ->and(SwissCompany::find('CHE-109.322.551'))->toBeNull()
        ->and(SwissCompany::validateUid('CHE-107.185.562'))->toBe(UidValidationResult::Valid)
        ->and(SwissCompany::validateUid('CHE-109.322.551'))->toBe(UidValidationResult::Invalid)
        ->and(SwissCompany::validateVatId('CHE-107.185.562'))->toBe(VatValidationResult::Active)
        ->and(SwissCompany::validateVatId('CHE-109.322.551'))->toBe(VatValidationResult::Inactive);

    $fake->assertLookedUp('CHE-107.185.562');
});

it('records searches for assertions', function (): void {
    $fake = SwissCompany::fake();

    $fake->assertNothingSearched();

    SwissCompany::search('boulangerie');

    $fake->assertSearched('boulangerie');
});

it('simulates registry outages', function (): void {
    $fake = SwissCompany::fake([CompanyFactory::new('CHE-107.185.562')->make()])->unavailable();

    expect(SwissCompany::validateUid('CHE-107.185.562'))->toBe(UidValidationResult::Unknown)
        ->and(SwissCompany::validateVatId('CHE-107.185.562'))->toBe(VatValidationResult::Unknown)
        ->and(fn () => SwissCompany::search('aubry'))->toThrow(RegistryUnavailableException::class);
});
