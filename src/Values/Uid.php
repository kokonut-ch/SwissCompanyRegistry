<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Values;

use Kokonut\SwissCompanyRegistry\Enums\VatSuffix;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidUidException;
use Stringable;

/**
 * Swiss enterprise identification number (UID/IDE, the base of the VAT
 * number "CHE-123.456.789 TVA").
 *
 * The 9th digit is a check digit (modulo 11, weights 5-4-3-2-7-6-5-4 over
 * the first 8 digits) as specified by the eCH-0097 standard. Parsing is
 * lenient about separators and tolerates the TVA/MWST/IVA suffixes as well
 * as bare 9-digit strings; a parsed instance always holds the canonical
 * "CHE123456789" form.
 */
final readonly class Uid implements Stringable
{
    private const array CHECK_DIGIT_WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4];

    /** @param string $value Canonical form, e.g. "CHE123456789". */
    private function __construct(public string $value) {}

    /**
     * @throws InvalidUidException when no plausible UID is found in the input
     */
    public static function parse(self|string|null $value): self
    {
        return self::tryParse($value) ?? throw InvalidUidException::for((string) $value);
    }

    public static function tryParse(self|string|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = mb_strtoupper($value);

        if (preg_match('/CHE\D*(\d(?:\D*\d){8})/', $value, $matches) === 1) {
            return new self('CHE'.preg_replace('/\D/', '', $matches[1]));
        }

        // Legacy inputs sometimes carry the 9 digits without the CHE prefix
        // (e.g. the old "TVA-123.456.789" mask).
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) === 9 ? new self('CHE'.$digits) : null;
    }

    /** Parseable and carrying a correct eCH-0097 check digit. */
    public static function isValid(self|string|null $value): bool
    {
        return self::tryParse($value)?->hasValidCheckDigit() ?? false;
    }

    /** The 9 digits without the CHE prefix. */
    public function digits(): string
    {
        return substr($this->value, 3);
    }

    public function hasValidCheckDigit(): bool
    {
        $digits = $this->digits();

        $sum = 0;

        foreach (self::CHECK_DIGIT_WEIGHTS as $index => $weight) {
            $sum += ((int) $digits[$index]) * $weight;
        }

        $check = 11 - ($sum % 11);

        if ($check === 10) {
            // No valid check digit exists for this combination: such a UID
            // is never issued.
            return false;
        }

        if ($check === 11) {
            $check = 0;
        }

        return $check === (int) $digits[8];
    }

    /** Official display format: "CHE-123.456.789". */
    public function format(): string
    {
        $digits = $this->digits();

        return sprintf('CHE-%s.%s.%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 3));
    }

    /** Official VAT number format: "CHE-123.456.789 TVA" (or MWST/IVA). */
    public function formatVat(VatSuffix $suffix = VatSuffix::TVA): string
    {
        return $this->format().' '.$suffix->value;
    }

    public function equals(self|string|null $other): bool
    {
        return self::tryParse($other)?->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
