<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Enums;

enum CompanyStatus: string
{
    case Active = 'ACTIVE';
    case Cancelled = 'CANCELLED';

    /**
     * Maps the eCH-0108 uidregStatusEnterpriseDetail code used by the UID
     * register. Only unambiguous codes are mapped; everything else
     * (provisional, in mutation, ...) yields null.
     */
    public static function fromUidRegisterCode(?int $code): ?self
    {
        return match ($code) {
            3 => self::Active,
            6, 7 => self::Cancelled,
            default => null,
        };
    }
}
