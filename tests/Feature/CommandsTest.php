<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Testing\CompanyFactory;
use Kokonut\SwissCompanyRegistry\Tests\Fixtures\ZefixFixtures;

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

it('shows the company and a capability-not-available line when validation is unsupported', function (): void {
    $this->app['config']->set('swiss-company-registry.fallback', false);
    $this->app['config']->set('swiss-company-registry.default', 'zefix');

    Http::fake(['www.zefix.admin.ch/*' => Http::response(ZefixFixtures::companyDetail())]);

    $this->artisan('swiss-company:lookup', ['uid' => 'CHE-107.185.562'])
        ->expectsOutputToContain('Boulangerie Aubry')
        ->expectsOutputToContain('not available with the current provider configuration')
        ->assertSuccessful();
});
