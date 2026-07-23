<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Enums\VatSuffix;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidUidException;
use Kokonut\SwissCompanyRegistry\Values\Uid;

describe('parsing', function (): void {
    it('normalizes common notations to CHE + 9 digits', function (string $input): void {
        expect(Uid::parse($input)->value)->toBe('CHE109322551');
    })->with([
        'CHE-109.322.551',
        'CHE-109.322.551 TVA',
        'CHE-109.322.551 MWST',
        'che 109 322 551 iva',
        'CHE109322551',
        // Legacy mask without the CHE prefix
        'TVA-109.322.551',
    ]);

    it('returns null from tryParse when no candidate is plausible', function (?string $input): void {
        expect(Uid::tryParse($input))->toBeNull();
    })->with([
        null,
        '',
        'CHE-123',
        'FR-123.456.789.012',
        'not a number',
    ]);

    it('throws from parse when no candidate is plausible', function (): void {
        Uid::parse('nope');
    })->throws(InvalidUidException::class);

    it('passes through existing instances', function (): void {
        $uid = Uid::parse('CHE-109.322.551');

        expect(Uid::parse($uid))->toBe($uid);
    });
});

describe('check digit', function (): void {
    it('accepts a correct eCH-0097 check digit', function (string $input): void {
        expect(Uid::isValid($input))->toBeTrue();
    })->with([
        'CHE-109.322.551',
        'CHE-109.322.551 TVA',
        'CHE-123.456.788',
    ]);

    it('rejects a wrong check digit', function (string $input): void {
        expect(Uid::isValid($input))->toBeFalse();
    })->with([
        'CHE-109.322.552',
        'CHE-123.456.789',
        'CHE-000.000.001',
    ]);

    it('rejects unparseable values', function (): void {
        expect(Uid::isValid('CHE-12'))->toBeFalse()
            ->and(Uid::isValid(null))->toBeFalse();
    });
});

describe('formatting', function (): void {
    it('formats with dot groups', function (): void {
        expect(Uid::parse('che109322551')->format())->toBe('CHE-109.322.551');
    });

    it('formats VAT numbers with a localized suffix', function (): void {
        $uid = Uid::parse('CHE109322551');

        expect($uid->formatVat())->toBe('CHE-109.322.551 TVA')
            ->and($uid->formatVat(VatSuffix::MWST))->toBe('CHE-109.322.551 MWST')
            ->and($uid->formatVat(VatSuffix::forLocale('it')))->toBe('CHE-109.322.551 IVA')
            ->and($uid->formatVat(VatSuffix::forLocale('en')))->toBe('CHE-109.322.551 TVA');
    });

    it('compares by canonical value and casts to string', function (): void {
        $uid = Uid::parse('CHE-109.322.551');

        expect($uid->equals('che 109322551'))->toBeTrue()
            ->and($uid->equals('CHE-109.322.552'))->toBeFalse()
            ->and((string) $uid)->toBe('CHE109322551')
            ->and($uid->digits())->toBe('109322551');
    });
});
