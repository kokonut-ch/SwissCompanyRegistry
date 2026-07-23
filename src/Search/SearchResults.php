<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Search;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Kokonut\SwissCompanyRegistry\Dto\CompanySummary;
use Traversable;

/**
 * @implements IteratorAggregate<int, CompanySummary>
 */
final readonly class SearchResults implements Countable, IteratorAggregate
{
    public function __construct(
        /** @var list<CompanySummary> */
        public array $companies,
        /** Which provider produced these results. */
        public string $provider,
    ) {}

    /** @return list<CompanySummary> */
    public function all(): array
    {
        return $this->companies;
    }

    public function first(): ?CompanySummary
    {
        return $this->companies[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->companies === [];
    }

    public function count(): int
    {
        return count($this->companies);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->companies);
    }

    public function take(int $limit): self
    {
        return new self(array_slice($this->companies, 0, $limit), $this->provider);
    }

    /**
     * Ready-made options for a select input, keyed by formatted UID:
     * ["CHE-123.456.788" => "Boulangerie Muster (Lausanne)", ...]
     *
     * @param  (callable(CompanySummary): string)|null  $label
     * @return array<string, string>
     */
    public function toSelectOptions(?callable $label = null): array
    {
        $label ??= fn (CompanySummary $company): string => $company->legalSeat === null
            ? $company->name
            : "{$company->name} ({$company->legalSeat})";

        $options = [];

        foreach ($this->companies as $company) {
            $options[$company->uid->format()] = $label($company);
        }

        return $options;
    }
}
