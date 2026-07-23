<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Contracts;

use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Values\Uid;

interface ValidatesUid extends RegistryProvider
{
    /**
     * Whether the UID is assigned to an entity in the register (valid
     * even when that entity has since been dissolved). Returns Unknown
     * instead of throwing when the registry cannot be reached.
     */
    public function validateUid(Uid $uid): UidValidationResult;
}
