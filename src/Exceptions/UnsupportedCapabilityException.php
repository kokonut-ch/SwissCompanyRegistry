<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Exceptions;

class UnsupportedCapabilityException extends SwissCompanyRegistryException
{
    public static function for(string $capability, bool $fallbackEnabled): self
    {
        $hint = $fallbackEnabled
            ? 'None of the configured providers offer it.'
            : 'The default provider does not offer it and fallback is disabled.';

        return new self("No provider available for {$capability}. {$hint}");
    }
}
