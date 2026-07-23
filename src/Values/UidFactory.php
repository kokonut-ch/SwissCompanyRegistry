<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Values;

use Kokonut\SwissCompanyRegistry\Enums\VatSuffix;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidUidException;

/**
 * Instance mirror of the static `Uid` API.
 *
 * It exists as the facade root so `SwissUid::parse()` works without
 * application code importing the value object class. `Uid` itself stays
 * the type carried across DTOs (`Company->uid`, etc.).
 */
class UidFactory
{
    /**
     * @throws InvalidUidException when no plausible UID is found in the input
     */
    public function parse(Uid|string|null $value): Uid
    {
        return Uid::parse($value);
    }

    public function tryParse(Uid|string|null $value): ?Uid
    {
        return Uid::tryParse($value);
    }

    /** Parseable and carrying a correct eCH-0097 check digit. */
    public function isValid(Uid|string|null $value): bool
    {
        return Uid::isValid($value);
    }

    /** Official display format: "CHE-123.456.789", or null when unparseable. */
    public function format(Uid|string|null $value): ?string
    {
        return $this->tryParse($value)?->format();
    }

    /** Official VAT number format: "CHE-123.456.789 TVA", or null when unparseable. */
    public function formatVat(Uid|string|null $value, VatSuffix $suffix = VatSuffix::TVA): ?string
    {
        return $this->tryParse($value)?->formatVat($suffix);
    }
}
