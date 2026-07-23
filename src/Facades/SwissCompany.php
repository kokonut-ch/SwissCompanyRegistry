<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Facades;

use Illuminate\Support\Facades\Facade;
use Kokonut\SwissCompanyRegistry\Dto\Company;
use Kokonut\SwissCompanyRegistry\SwissCompanyRegistry;
use Kokonut\SwissCompanyRegistry\Testing\SwissCompanyRegistryFake;

/**
 * @method static \Kokonut\SwissCompanyRegistry\Search\SearchResults search(\Kokonut\SwissCompanyRegistry\Search\SearchQuery|string $query)
 * @method static \Kokonut\SwissCompanyRegistry\Search\SearchResults suggest(string $term, int $limit = 10, \Kokonut\SwissCompanyRegistry\Enums\Canton|string|null $canton = null)
 * @method static \Kokonut\SwissCompanyRegistry\Dto\Company|null find(\Kokonut\SwissCompanyRegistry\Values\Uid|string $uid)
 * @method static \Kokonut\SwissCompanyRegistry\Enums\UidValidationResult validateUid(\Kokonut\SwissCompanyRegistry\Values\Uid|string|null $uid)
 * @method static \Kokonut\SwissCompanyRegistry\Enums\VatValidationResult validateVatId(\Kokonut\SwissCompanyRegistry\Values\Uid|string|null $uid)
 * @method static \Kokonut\SwissCompanyRegistry\Contracts\RegistryProvider provider(?string $name = null)
 * @method static \Kokonut\SwissCompanyRegistry\SwissCompanyRegistry extend(string $name, \Closure $creator)
 *
 * @see SwissCompanyRegistry
 */
class SwissCompany extends Facade
{
    /**
     * Replace the manager with an in-memory fake for testing.
     *
     * @param  list<Company>  $companies
     */
    public static function fake(array $companies = []): SwissCompanyRegistryFake
    {
        $fake = new SwissCompanyRegistryFake($companies);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return SwissCompanyRegistry::class;
    }
}
