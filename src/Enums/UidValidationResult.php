<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Enums;

/**
 * Outcome of a UID validation against the register. A UID is Valid when
 * it is assigned to an entity, even one that has since been dissolved.
 * Unknown means the register could not be consulted; treat it as
 * "could not verify", never as a rejection.
 */
enum UidValidationResult
{
    case Valid;
    case Invalid;
    case Unknown;

    public function isValid(): bool
    {
        return $this === self::Valid;
    }

    /** False only when the register could not be consulted. */
    public function isKnown(): bool
    {
        return $this !== self::Unknown;
    }

    public static function fromBool(?bool $value): self
    {
        return match ($value) {
            true => self::Valid,
            false => self::Invalid,
            null => self::Unknown,
        };
    }
}
