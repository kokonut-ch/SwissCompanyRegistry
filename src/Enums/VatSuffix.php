<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Enums;

/**
 * Official language suffixes of the Swiss VAT number. There is no
 * English variant; TVA is used as the fallback for other locales.
 */
enum VatSuffix: string
{
    case TVA = 'TVA';
    case MWST = 'MWST';
    case IVA = 'IVA';

    public static function forLocale(?string $locale): self
    {
        return match (substr((string) $locale, 0, 2)) {
            'de' => self::MWST,
            'it' => self::IVA,
            default => self::TVA,
        };
    }
}
