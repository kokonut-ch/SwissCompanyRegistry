<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Dto\Address;

describe('lines', function (): void {
    it('lists careOf, street, poBox and city in postal order', function (): void {
        $address = new Address(
            careOf: 'p.a. Famille Muster',
            street: 'Rue du Marché',
            houseNumber: '12',
            poBox: 'Case postale 123',
            zipCode: '1003',
            city: 'Lausanne',
        );

        expect($address->lines())->toBe([
            'p.a. Famille Muster',
            'Rue du Marché 12',
            'Case postale 123',
            '1003 Lausanne',
        ]);
    });

    it('filters out unknown parts and keeps only what is known', function (): void {
        $address = new Address(city: 'Lausanne');

        expect($address->lines())->toBe(['Lausanne']);
    });

    it('returns an empty list when nothing is known', function (): void {
        expect((new Address)->lines())->toBe([]);
    });
});

describe('block', function (): void {
    it('joins the lines with newlines', function (): void {
        $address = new Address(
            careOf: 'p.a. Famille Muster',
            street: 'Rue du Marché',
            houseNumber: '12',
            poBox: 'Case postale 123',
            zipCode: '1003',
            city: 'Lausanne',
        );

        expect($address->block())->toBe("p.a. Famille Muster\nRue du Marché 12\nCase postale 123\n1003 Lausanne");
    });

    it('returns null for an empty address', function (): void {
        expect((new Address)->block())->toBeNull();
    });
});

describe('poBoxLine', function (): void {
    it('passes the raw post office box through unchanged', function (): void {
        expect((new Address(poBox: 'Case postale 123'))->poBoxLine())->toBe('Case postale 123')
            ->and((new Address)->poBoxLine())->toBeNull();
    });
});

describe('toArray', function (): void {
    it('exposes the raw properties', function (): void {
        $address = new Address(
            careOf: 'p.a. Famille Muster',
            street: 'Rue du Marché',
            houseNumber: '12',
            addon: 'Building A',
            poBox: 'Case postale 123',
            zipCode: '1003',
            city: 'Lausanne',
            country: 'CH',
        );

        expect($address->toArray())->toBe([
            'careOf' => 'p.a. Famille Muster',
            'street' => 'Rue du Marché',
            'houseNumber' => '12',
            'addon' => 'Building A',
            'poBox' => 'Case postale 123',
            'zipCode' => '1003',
            'city' => 'Lausanne',
            'country' => 'CH',
        ]);
    });
});

describe('oneLine', function (): void {
    it('does not include careOf or poBox', function (): void {
        $address = new Address(
            careOf: 'p.a. Famille Muster',
            street: 'Rue du Marché',
            houseNumber: '12',
            poBox: 'Case postale 123',
            zipCode: '1003',
            city: 'Lausanne',
        );

        expect($address->oneLine())->toBe('Rue du Marché 12, 1003 Lausanne');
    });
});
