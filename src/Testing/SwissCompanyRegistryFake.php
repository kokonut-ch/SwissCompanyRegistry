<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Testing;

use Kokonut\SwissCompanyRegistry\Dto\Company;
use Kokonut\SwissCompanyRegistry\Dto\CompanySummary;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Search\SearchResults;
use Kokonut\SwissCompanyRegistry\SwissCompanyRegistry;
use Kokonut\SwissCompanyRegistry\Values\Uid;
use PHPUnit\Framework\Assert;

/**
 * In-memory manager for tests: seeded with Company DTOs, no network,
 * no cache. Swap it in with SwissCompany::fake([...]).
 */
class SwissCompanyRegistryFake extends SwissCompanyRegistry
{
    /** @var list<SearchQuery> */
    public array $searches = [];

    /** @var list<Uid> */
    public array $finds = [];

    /** @var list<Uid> */
    public array $uidValidations = [];

    /** @var list<Uid> */
    public array $vatValidations = [];

    private bool $unavailable = false;

    /** @param list<Company> $companies */
    public function __construct(private readonly array $companies = [])
    {
        parent::__construct(['cache' => ['enabled' => false], 'fallback' => false]);
    }

    /** Make every subsequent call behave as if the registries were down. */
    public function unavailable(bool $unavailable = true): static
    {
        $this->unavailable = $unavailable;

        return $this;
    }

    public function search(SearchQuery|string $query): SearchResults
    {
        $query = is_string($query) ? SearchQuery::make($query) : $query;

        $this->searches[] = $query;

        if ($this->unavailable) {
            throw RegistryUnavailableException::maintenance('fake', 503);
        }

        $needle = mb_strtolower($query->bareName());

        $matches = array_values(array_filter(
            $this->companies,
            function (Company $company) use ($query, $needle): bool {
                $name = mb_strtolower($company->name);

                $nameMatches = $query->fuzzy
                    ? str_contains($name, $needle)
                    : str_starts_with($name, $needle);

                return $nameMatches
                    && ($query->canton === null || $company->canton === $query->canton)
                    && ($query->legalForm === null || $company->legalForm === $query->legalForm)
                    && (! $query->activeOnly || $company->isActive());
            },
        ));

        if ($query->limit !== null) {
            $matches = array_slice($matches, 0, $query->limit);
        }

        return new SearchResults(
            array_map(fn (Company $company): CompanySummary => $company->summary(), $matches),
            'fake',
        );
    }

    public function find(Uid|string $uid): ?Company
    {
        $uid = Uid::parse($uid);

        $this->finds[] = $uid;

        if ($this->unavailable) {
            throw RegistryUnavailableException::maintenance('fake', 503);
        }

        return $this->companyFor($uid);
    }

    public function validateUid(Uid|string|null $uid): UidValidationResult
    {
        $uid = Uid::tryParse($uid);

        if ($uid === null) {
            return UidValidationResult::Invalid;
        }

        $this->uidValidations[] = $uid;

        if ($this->unavailable) {
            return UidValidationResult::Unknown;
        }

        return $this->companyFor($uid) === null
            ? UidValidationResult::Invalid
            : UidValidationResult::Valid;
    }

    public function validateVatId(Uid|string|null $uid): VatValidationResult
    {
        $uid = Uid::tryParse($uid);

        if ($uid === null) {
            return VatValidationResult::Inactive;
        }

        $this->vatValidations[] = $uid;

        if ($this->unavailable) {
            return VatValidationResult::Unknown;
        }

        return $this->companyFor($uid)?->vatRegistered === true
            ? VatValidationResult::Active
            : VatValidationResult::Inactive;
    }

    public function assertSearched(string $term): void
    {
        Assert::assertTrue(
            collect($this->searches)->contains(
                fn (SearchQuery $query): bool => str_contains(mb_strtolower($query->bareName()), mb_strtolower($term)),
            ),
            "No search for \"{$term}\" was performed.",
        );
    }

    public function assertNothingSearched(): void
    {
        Assert::assertSame([], $this->searches, 'Unexpected searches were performed.');
    }

    public function assertLookedUp(Uid|string $uid): void
    {
        $uid = Uid::parse($uid);

        Assert::assertTrue(
            collect($this->finds)->contains(fn (Uid $find): bool => $find->equals($uid)),
            "No lookup for \"{$uid->format()}\" was performed.",
        );
    }

    private function companyFor(Uid $uid): ?Company
    {
        foreach ($this->companies as $company) {
            if ($company->uid->equals($uid)) {
                return $company;
            }
        }

        return null;
    }
}
