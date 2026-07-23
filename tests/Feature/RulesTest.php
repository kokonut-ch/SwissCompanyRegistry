<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Rules\ActiveVatNumber;
use Kokonut\SwissCompanyRegistry\Rules\RegisteredUid;
use Kokonut\SwissCompanyRegistry\Rules\ValidUid;
use Kokonut\SwissCompanyRegistry\Testing\CompanyFactory;

it('validates the UID format offline', function (): void {
    $passes = Validator::make(['uid' => 'CHE-109.322.551'], ['uid' => new ValidUid])->passes();
    $fails = Validator::make(['uid' => 'CHE-109.322.552'], ['uid' => new ValidUid])->fails();

    expect($passes)->toBeTrue()->and($fails)->toBeTrue();
});

it('checks registration in the UID register', function (): void {
    SwissCompany::fake([CompanyFactory::new('CHE-109.322.551')->make()]);

    expect(Validator::make(['uid' => 'CHE-109.322.551'], ['uid' => new RegisteredUid])->passes())->toBeTrue()
        ->and(Validator::make(['uid' => 'CHE-123.456.788'], ['uid' => new RegisteredUid])->fails())->toBeTrue();
});

it('passes softly when the register is unreachable, unless strict', function (): void {
    SwissCompany::fake([CompanyFactory::new('CHE-109.322.551')->make()])->unavailable();

    expect(Validator::make(['uid' => 'CHE-109.322.551'], ['uid' => new RegisteredUid])->passes())->toBeTrue()
        ->and(Validator::make(['uid' => 'CHE-109.322.551'], ['uid' => RegisteredUid::strict()])->fails())->toBeTrue();
});

it('never lets an unsupported capability escape as an exception to the validator', function (): void {
    $this->app['config']->set('swiss-company-registry.providers', ['zefix' => ['username' => 'u', 'password' => 'p']]);
    $this->app['config']->set('swiss-company-registry.default', 'zefix');

    expect(Validator::make(['uid' => 'CHE-109.322.551'], ['uid' => new RegisteredUid])->passes())->toBeTrue()
        ->and(Validator::make(['uid' => 'CHE-109.322.551'], ['uid' => RegisteredUid::strict()])->fails())->toBeTrue();
});

it('checks active VAT registration', function (): void {
    SwissCompany::fake([
        CompanyFactory::new('CHE-109.322.551')->vatRegistered()->make(),
        CompanyFactory::new('CHE-123.456.788')->make(),
    ]);

    expect(Validator::make(['vat' => 'CHE-109.322.551 TVA'], ['vat' => new ActiveVatNumber])->passes())->toBeTrue()
        ->and(Validator::make(['vat' => 'CHE-123.456.788'], ['vat' => new ActiveVatNumber])->fails())->toBeTrue()
        ->and(Validator::make(['vat' => 'garbage'], ['vat' => new ActiveVatNumber])->fails())->toBeTrue();
});
