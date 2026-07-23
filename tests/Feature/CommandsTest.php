<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Testing\CompanyFactory;

it('searches from the console', function (): void {
    SwissCompany::fake([
        CompanyFactory::new('CHE-107.185.562')->name('Boulangerie Muster')->make(),
    ]);

    $this->artisan('swiss-company:search', ['name' => 'boulangerie'])
        ->expectsOutputToContain('Boulangerie Muster')
        ->assertSuccessful();
});

it('reports empty search results', function (): void {
    SwissCompany::fake();

    $this->artisan('swiss-company:search', ['name' => 'nothing'])
        ->expectsOutputToContain('No company found.')
        ->assertSuccessful();
});

it('rejects an unknown canton option', function (): void {
    SwissCompany::fake();

    $this->artisan('swiss-company:search', ['name' => 'aubry', '--canton' => 'XX'])
        ->assertFailed();
});

it('looks up a company and its validation state from the console', function (): void {
    SwissCompany::fake([
        CompanyFactory::new('CHE-107.185.562')->vatRegistered()->make(),
    ]);

    $this->artisan('swiss-company:lookup', ['uid' => 'CHE-107.185.562'])
        ->expectsOutputToContain('Boulangerie Muster')
        ->assertSuccessful();
});

it('rejects an unparseable UID from the console', function (): void {
    SwissCompany::fake();

    $this->artisan('swiss-company:lookup', ['uid' => 'nope'])
        ->assertFailed();
});
