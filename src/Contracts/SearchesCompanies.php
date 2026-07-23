<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Contracts;

use Kokonut\SwissCompanyRegistry\Exceptions\InvalidSearchQueryException;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Exceptions\TooManyResultsException;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Search\SearchResults;

interface SearchesCompanies extends RegistryProvider
{
    /**
     * @throws RegistryUnavailableException
     * @throws TooManyResultsException
     * @throws InvalidSearchQueryException
     */
    public function search(SearchQuery $query): SearchResults;

    /**
     * Whether this provider can honor every filter of the given query.
     * The manager skips providers that cannot, instead of silently
     * ignoring filters.
     */
    public function supports(SearchQuery $query): bool;
}
