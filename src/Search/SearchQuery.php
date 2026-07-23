<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Search;

use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidSearchQueryException;

/**
 * Provider-agnostic search query. Not every provider honors every
 * filter; the manager routes the query to the first provider that
 * supports the exact combination in use.
 */
final class SearchQuery
{
    public bool $fuzzy = false;

    public bool $activeOnly = true;

    public ?Canton $canton = null;

    public ?LegalForm $legalForm = null;

    /** Commune of the legal seat; supported by the UID register only. */
    public ?string $town = null;

    /** Swiss zip code; supported by the UID register only. */
    public ?string $zipCode = null;

    /** BFS commune number; supported by Zefix only. */
    public ?int $legalSeatId = null;

    /** Cantonal registry-of-commerce office id; supported by Zefix only. */
    public ?int $registryOfCommerceId = null;

    public ?int $limit = null;

    private function __construct(public string $name)
    {
        if (trim($name, " \t*") === '') {
            throw new InvalidSearchQueryException('A search needs a non-empty company name.');
        }
    }

    public static function make(string $name): self
    {
        return new self(trim($name));
    }

    /**
     * Match anywhere in the name instead of only from its beginning.
     * This is what turns "muster" into a hit for "Boulangerie Muster".
     */
    public function fuzzy(bool $fuzzy = true): self
    {
        $this->fuzzy = $fuzzy;

        return $this;
    }

    public function includeInactive(bool $include = true): self
    {
        $this->activeOnly = ! $include;

        return $this;
    }

    public function canton(Canton|string|null $canton): self
    {
        if (is_string($canton)) {
            $canton = Canton::tryFrom(strtoupper($canton))
                ?? throw new InvalidSearchQueryException("Unknown canton abbreviation \"{$canton}\".");
        }

        if ($canton !== null && ($this->legalSeatId !== null || $this->registryOfCommerceId !== null)) {
            throw new InvalidSearchQueryException('A canton filter cannot be combined with a commune or registry-office filter.');
        }

        $this->canton = $canton;

        return $this;
    }

    public function legalForm(LegalForm|string|null $legalForm): self
    {
        if (is_string($legalForm)) {
            $legalForm = LegalForm::tryFrom($legalForm)
                ?? throw new InvalidSearchQueryException("Unknown eCH-0097 legal form code \"{$legalForm}\".");
        }

        $this->legalForm = $legalForm;

        return $this;
    }

    public function town(?string $town): self
    {
        $this->town = $town;

        return $this;
    }

    public function zipCode(?string $zipCode): self
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function legalSeatId(?int $bfsCommuneId): self
    {
        if ($bfsCommuneId !== null && $this->canton !== null) {
            throw new InvalidSearchQueryException('A commune filter cannot be combined with a canton filter.');
        }

        $this->legalSeatId = $bfsCommuneId;

        return $this;
    }

    public function registryOfCommerceId(?int $officeId): self
    {
        if ($officeId !== null && $this->canton !== null) {
            throw new InvalidSearchQueryException('A registry-office filter cannot be combined with a canton filter.');
        }

        $this->registryOfCommerceId = $officeId;

        return $this;
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /** The term stripped of any wildcards the caller may have typed. */
    public function bareName(): string
    {
        return trim($this->name, " \t*");
    }

    /** The name as sent to wildcard-based registries such as Zefix. */
    public function effectiveName(): string
    {
        return $this->fuzzy ? '*'.$this->bareName().'*' : $this->name;
    }

    /** Stable identity of the query, used for cache keys. */
    public function fingerprint(): string
    {
        return sha1(json_encode([
            $this->name,
            $this->fuzzy,
            $this->activeOnly,
            $this->canton?->value,
            $this->legalForm?->value,
            $this->town,
            $this->zipCode,
            $this->legalSeatId,
            $this->registryOfCommerceId,
            $this->limit,
        ], JSON_THROW_ON_ERROR));
    }
}
