<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Tests\Fixtures;

/**
 * Payloads mirroring real Zefix REST API responses (public data,
 * captured July 2026).
 */
final class ZefixFixtures
{
    /** @return list<array<string, mixed>> */
    public static function searchResults(): array
    {
        return [
            [
                'name' => 'Aubry Carrelage Sàrl',
                'ehraid' => 1741228,
                'uid' => 'CHE319027296',
                'chid' => 'CH67040106731',
                'legalSeatId' => 6709,
                'legalSeat' => 'Courroux',
                'registryOfCommerceId' => 670,
                'legalForm' => self::legalForm(4, '0107'),
                'status' => 'ACTIVE',
                'sogcDate' => '2026-03-23',
                'deletionDate' => null,
            ],
            [
                'name' => 'Boulangerie Aubry',
                'ehraid' => 677863,
                'uid' => 'CHE107185562',
                'chid' => 'CH67010028221',
                'legalSeatId' => 6711,
                'legalSeat' => 'Delémont',
                'registryOfCommerceId' => 670,
                'legalForm' => self::legalForm(1, '0101'),
                'status' => 'ACTIVE',
                'sogcDate' => '2002-06-25',
                'deletionDate' => null,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function companyDetail(): array
    {
        return [
            [
                'name' => 'Boulangerie Aubry',
                'ehraid' => 677863,
                'uid' => 'CHE107185562',
                'chid' => 'CH67010028221',
                'legalSeatId' => 6711,
                'legalSeat' => 'Delémont',
                'registryOfCommerceId' => 670,
                'legalForm' => self::legalForm(1, '0101'),
                'status' => 'ACTIVE',
                'sogcDate' => '2002-06-25',
                'deletionDate' => null,
                'purpose' => "Exploitation d'une boulangerie-pâtisserie",
                'address' => [
                    'organisation' => 'Boulangerie Aubry',
                    'careOf' => 'p.a. Famille Aubry',
                    'street' => 'Rue Pierre Péquignat',
                    'houseNumber' => '8',
                    'addon' => null,
                    'poBox' => null,
                    'city' => 'Delémont',
                    'swissZipCode' => '2800',
                ],
                'canton' => 'JU',
                'capitalNominal' => null,
                'capitalCurrency' => 'CHF',
                'cantonalExcerptWeb' => 'https://ju.chregister.ch/cr-portal/auszug/auszug.xhtml?uid=CHE-107.185.562',
                'zefixDetailWeb' => [
                    'en' => 'https://www.zefix.admin.ch/en/search/entity/list?name=CHE107185562&directLink=true',
                    'fr' => 'https://www.zefix.admin.ch/fr/search/entity/list?name=CHE107185562&directLink=true',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function legalForm(int $id, string $uid): array
    {
        $names = [
            '0101' => ['de' => 'Einzelunternehmen', 'fr' => 'Entreprise individuelle', 'it' => 'Ditta individuale', 'en' => 'Sole proprietorship'],
            '0107' => ['de' => 'Gesellschaft mit beschränkter Haftung', 'fr' => 'Société à responsabilité limitée', 'it' => 'Società a garanzia limitata', 'en' => 'Limited Liability Company'],
        ];

        return ['id' => $id, 'uid' => $uid, 'name' => $names[$uid] ?? [], 'shortName' => []];
    }
}
