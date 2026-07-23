<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Contracts;

use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Values\Uid;

interface ValidatesVat extends RegistryProvider
{
    /**
     * Whether the number belongs to an ACTIVE Swiss VAT registration.
     * Returns Unknown instead of throwing when the registry cannot be
     * reached.
     */
    public function validateVatId(Uid $uid): VatValidationResult;
}
