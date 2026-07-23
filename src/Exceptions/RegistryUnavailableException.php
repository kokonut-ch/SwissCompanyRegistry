<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Exceptions;

/**
 * The registry is temporarily unreachable (maintenance window, network
 * failure, rate limiting). Retrying later is expected to succeed;
 * applications typically surface this as a "service unavailable" notice.
 */
class RegistryUnavailableException extends SwissCompanyRegistryException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }

    public static function maintenance(string $provider, int $status): self
    {
        return new self($provider, "The {$provider} registry is temporarily unavailable (HTTP {$status}).", $status);
    }

    public static function unreachable(string $provider, string $reason): self
    {
        return new self($provider, "The {$provider} registry could not be reached: {$reason}");
    }

    public static function rateLimited(string $provider): self
    {
        return new self($provider, "The {$provider} registry rejected the request because of rate limiting.");
    }
}
