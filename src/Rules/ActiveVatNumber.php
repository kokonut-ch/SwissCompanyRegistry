<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\UnsupportedCapabilityException;
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Values\Uid;

/**
 * Online validation: the number must belong to an ACTIVE Swiss VAT
 * registration. When the register cannot be reached the rule passes
 * (the format has already been checked), unless strict mode is
 * requested.
 */
class ActiveVatNumber implements ValidationRule
{
    public function __construct(private readonly bool $strict = false) {}

    /** Fail instead of passing when the register cannot be reached. */
    public static function strict(): self
    {
        return new self(strict: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Uid::isValid($value)) {
            $fail('The :attribute field must be a valid Swiss VAT number (CHE-123.456.789 TVA).');

            return;
        }

        // A misconfigured provider chain must never turn a form submission
        // into a 500: no provider offering the capability is treated the
        // same as an unreachable one (Unknown), not as a hard failure.
        try {
            $result = SwissCompany::validateVatId($value);
        } catch (UnsupportedCapabilityException) {
            $result = VatValidationResult::Unknown;
        }

        if ($result === VatValidationResult::Inactive) {
            $fail('The :attribute field is not an active Swiss VAT registration.');
        }

        if ($this->strict && $result === VatValidationResult::Unknown) {
            $fail('The :attribute field could not be verified against the Swiss VAT register.');
        }
    }
}
