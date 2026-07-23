<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Enums;

use Kokonut\SwissCompanyRegistry\Support\DisplayLocale;

/**
 * Official language suffixes of the Swiss VAT number. There is no
 * English variant; TVA is used as the fallback for other locales.
 */
enum VatSuffix: string
{
    case TVA = 'TVA';
    case MWST = 'MWST';
    case IVA = 'IVA';

    /**
     * @param  'de'|'fr'|'it'|'en'|string|null  $locale  Defaults to the
     *                                                   configured display locale (see `DisplayLocale::resolve()`).
     */
    public static function forLocale(?string $locale = null): self
    {
        $locale = $locale === null ? DisplayLocale::resolve() : $locale;

        return match (substr($locale, 0, 2)) {
            'de' => self::MWST,
            'it' => self::IVA,
            default => self::TVA,
        };
    }
}
