<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Enums\VatSuffix;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;

it('labels cantons in the four languages', function (): void {
    expect(Canton::GE->label('fr'))->toBe('Genève')
        ->and(Canton::GE->label('de'))->toBe('Genf')
        ->and(Canton::GE->label('it'))->toBe('Ginevra')
        ->and(Canton::GE->label())->toBe('Geneva')
        ->and(Canton::JU->label('pt'))->toBe('Jura');
});

it('exposes all 26 cantons', function (): void {
    expect(Canton::cases())->toHaveCount(26);
});

it('maps legal forms to and from Zefix ids', function (): void {
    foreach (LegalForm::cases() as $legalForm) {
        expect(LegalForm::fromZefixId($legalForm->zefixId()))->toBe($legalForm);
    }

    expect(LegalForm::fromZefixId(999))->toBeNull()
        ->and(LegalForm::fromZefixId(null))->toBeNull();
});

it('labels legal forms in the four languages', function (): void {
    expect(LegalForm::LimitedLiabilityCompany->label('fr'))->toBe('Société à responsabilité limitée')
        ->and(LegalForm::LimitedLiabilityCompany->shortLabel('fr'))->toBe('Sàrl')
        ->and(LegalForm::Corporation->shortLabel('de'))->toBe('AG')
        ->and(LegalForm::Corporation->label())->toBe('Corporation');
});

it('maps UID register status codes conservatively', function (): void {
    expect(CompanyStatus::fromUidRegisterCode(3))->toBe(CompanyStatus::Active)
        ->and(CompanyStatus::fromUidRegisterCode(6))->toBe(CompanyStatus::Cancelled)
        ->and(CompanyStatus::fromUidRegisterCode(7))->toBe(CompanyStatus::Cancelled)
        ->and(CompanyStatus::fromUidRegisterCode(1))->toBeNull()
        ->and(CompanyStatus::fromUidRegisterCode(null))->toBeNull();
});

it('exposes tri-state validation helpers', function (): void {
    expect(UidValidationResult::fromBool(true)->isValid())->toBeTrue()
        ->and(UidValidationResult::fromBool(null)->isKnown())->toBeFalse()
        ->and(UidValidationResult::fromBool(false)->isKnown())->toBeTrue()
        ->and(VatValidationResult::fromBool(true)->isActive())->toBeTrue()
        ->and(VatValidationResult::fromBool(false)->isActive())->toBeFalse()
        ->and(VatValidationResult::fromBool(null)->isKnown())->toBeFalse();
});

it('picks the VAT suffix from the locale', function (): void {
    expect(VatSuffix::forLocale('de_CH'))->toBe(VatSuffix::MWST)
        ->and(VatSuffix::forLocale('it'))->toBe(VatSuffix::IVA)
        ->and(VatSuffix::forLocale('fr'))->toBe(VatSuffix::TVA)
        ->and(VatSuffix::forLocale(null))->toBe(VatSuffix::TVA);
});
