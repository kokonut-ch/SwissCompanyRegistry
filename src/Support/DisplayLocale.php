<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Support;

use Throwable;

/**
 * Resolves the package-wide display locale driving enum labels
 * (Canton, LegalForm), the default VAT suffix (VatSuffix::forLocale())
 * and the Zefix detail link. The registry data itself is always
 * language-neutral; this only decides how it is displayed.
 */
final class DisplayLocale
{
    /**
     * Resolution order: the explicit argument, then the
     * "swiss-company-registry.locale" config value, then the
     * application locale, then "en". Safe to call outside a booted
     * Laravel application: any failure while reading config()/app()
     * falls back to "en".
     */
    public static function resolve(?string $locale = null): string
    {
        if ($locale !== null && $locale !== '') {
            return self::normalize($locale);
        }

        try {
            $configured = config('swiss-company-registry.locale');

            if (is_string($configured) && $configured !== '') {
                return self::normalize($configured);
            }

            $appLocale = app()->getLocale();

            return self::normalize($appLocale);
        } catch (Throwable) {
            return 'en';
        }
    }

    private static function normalize(string $locale): string
    {
        return substr(strtolower($locale), 0, 2);
    }
}
