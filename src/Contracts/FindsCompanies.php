<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Contracts;

use Kokonut\SwissCompanyRegistry\Dto\Company;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Values\Uid;

interface FindsCompanies extends RegistryProvider
{
    /**
     * Full company record for a known UID, or null when the registry
     * has no entity under that number.
     *
     * @throws RegistryUnavailableException
     */
    public function find(Uid $uid): ?Company;
}
