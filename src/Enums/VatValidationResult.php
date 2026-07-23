<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Enums;

/**
 * Outcome of a VAT number validation. The register only reports Active
 * for numbers with a currently active VAT registration; Inactive covers
 * both unknown numbers and terminated registrations. Unknown means the
 * register could not be consulted; treat it as "could not verify",
 * never as a rejection.
 */
enum VatValidationResult
{
    case Active;
    case Inactive;
    case Unknown;

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /** False only when the register could not be consulted. */
    public function isKnown(): bool
    {
        return $this !== self::Unknown;
    }

    public static function fromBool(?bool $value): self
    {
        return match ($value) {
            true => self::Active,
            false => self::Inactive,
            null => self::Unknown,
        };
    }
}
