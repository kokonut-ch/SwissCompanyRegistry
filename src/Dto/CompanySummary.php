<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Dto;

use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Values\Uid;

/**
 * One search hit. Lightweight on purpose: fetch the full record with
 * SwissCompany::find($summary->uid) when more than the essentials are
 * needed. Fields a registry does not expose in its result list stay null.
 */
final readonly class CompanySummary
{
    public function __construct(
        public Uid $uid,
        public string $name,
        /** Commune of the legal seat, e.g. "Lausanne". */
        public ?string $legalSeat = null,
        public ?Canton $canton = null,
        public ?LegalForm $legalForm = null,
        public ?CompanyStatus $status = null,
        /** Commercial register number, e.g. "CH67010028221". */
        public ?string $chId = null,
        public ?int $ehraId = null,
        /** Match confidence 0-100, only set by registries that score hits. */
        public ?int $rating = null,
        /** @var array<array-key, mixed> Untouched registry payload for anything not mapped above. */
        public array $raw = [],
    ) {}

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }
}
