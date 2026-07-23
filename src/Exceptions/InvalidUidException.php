<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Exceptions;

class InvalidUidException extends SwissCompanyRegistryException
{
    public static function for(string $value): self
    {
        return new self("\"{$value}\" cannot be parsed as a Swiss UID (expected CHE + 9 digits).");
    }
}
