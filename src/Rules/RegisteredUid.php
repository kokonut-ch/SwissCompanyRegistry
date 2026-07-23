<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Values\Uid;

/**
 * Online validation: the UID must exist in the Swiss UID register.
 * When the register cannot be reached the rule passes (the format has
 * already been checked), unless strict mode is requested.
 */
class RegisteredUid implements ValidationRule
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
            $fail('The :attribute field must be a valid Swiss UID number (CHE-123.456.789).');

            return;
        }

        $result = SwissCompany::validateUid($value);

        if ($result === UidValidationResult::Invalid) {
            $fail('The :attribute field is not registered in the Swiss UID register.');
        }

        if ($this->strict && $result === UidValidationResult::Unknown) {
            $fail('The :attribute field could not be verified against the Swiss UID register.');
        }
    }
}
