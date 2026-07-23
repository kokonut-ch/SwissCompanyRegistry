<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Exceptions;

class UnexpectedResponseException extends SwissCompanyRegistryException
{
    public static function fromStatus(string $provider, int $status, ?string $detail = null): self
    {
        $message = "The {$provider} registry returned an unexpected HTTP {$status} response.";

        return new self($detail === null ? $message : "{$message} {$detail}");
    }
}
