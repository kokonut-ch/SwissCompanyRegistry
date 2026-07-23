<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Kokonut\SwissCompanyRegistry\Support\DisplayLocale;

it('lets an explicit argument win over config and the application locale', function (): void {
    config()->set('swiss-company-registry.locale', 'de');
    app()->setLocale('it');

    expect(DisplayLocale::resolve('fr'))->toBe('fr');
});

it('prefers the configured locale over the application locale', function (): void {
    config()->set('swiss-company-registry.locale', 'de');
    app()->setLocale('fr');

    expect(DisplayLocale::resolve())->toBe('de');
});

it('falls back to the application locale when no locale is configured', function (): void {
    config()->set('swiss-company-registry.locale', null);
    app()->setLocale('de');

    expect(DisplayLocale::resolve())->toBe('de');
});

it('falls back to English when no booted application is available', function (): void {
    $original = Container::getInstance();

    Container::setInstance(null);

    try {
        expect(DisplayLocale::resolve())->toBe('en');
    } finally {
        Container::setInstance($original);
    }
});

it('normalizes locales to their lowercase two-letter code', function (): void {
    expect(DisplayLocale::resolve('de_CH'))->toBe('de')
        ->and(DisplayLocale::resolve('FR'))->toBe('fr');
});
