<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Enums\VatSuffix;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidUidException;
use Kokonut\SwissCompanyRegistry\Facades\SwissUid;
use Kokonut\SwissCompanyRegistry\Values\Uid;

it('parses lenient input into a Uid instance', function (): void {
    $uid = SwissUid::parse('che 109 322 551');

    expect($uid)->toBeInstanceOf(Uid::class)
        ->and($uid->value)->toBe('CHE109322551');
});

it('throws from parse when no candidate is plausible', function (): void {
    SwissUid::parse('nope');
})->throws(InvalidUidException::class);

it('returns null from tryParse when no candidate is plausible', function (): void {
    expect(SwissUid::tryParse('not a number'))->toBeNull();
});

it('checks the eCH-0097 check digit', function (): void {
    expect(SwissUid::isValid('CHE-109.322.551'))->toBeTrue()
        ->and(SwissUid::isValid('CHE-109.322.552'))->toBeFalse();
});

it('formats a parseable value and returns null otherwise', function (): void {
    expect(SwissUid::format('che 109 322 551'))->toBe('CHE-109.322.551')
        ->and(SwissUid::format('not a number'))->toBeNull();
});

it('formats a VAT number with a localized suffix and returns null otherwise', function (): void {
    expect(SwissUid::formatVat('che 109 322 551'))->toBe('CHE-109.322.551 TVA')
        ->and(SwissUid::formatVat('che 109 322 551', VatSuffix::MWST))->toBe('CHE-109.322.551 MWST')
        ->and(SwissUid::formatVat('not a number'))->toBeNull();
});

it('is reachable through the "SwissUid" alias registered for the package', function (): void {
    expect(\SwissUid::isValid('CHE-109.322.551'))->toBeTrue()
        ->and(\SwissUid::isValid('CHE-109.322.552'))->toBeFalse();
});
