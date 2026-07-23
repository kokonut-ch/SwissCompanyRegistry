<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Exceptions;

class UnsupportedCapabilityException extends SwissCompanyRegistryException
{
    public static function for(string $capability): self
    {
        return new self("No configured provider offers {$capability}.");
    }
}
