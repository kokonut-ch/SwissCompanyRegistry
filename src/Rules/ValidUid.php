<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kokonut\SwissCompanyRegistry\Values\Uid;

/**
 * Offline validation: parseable as a Swiss UID and carrying a correct
 * eCH-0097 check digit. No network call.
 */
class ValidUid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Uid::isValid($value)) {
            $fail('The :attribute field must be a valid Swiss UID number (CHE-123.456.789).');
        }
    }
}
