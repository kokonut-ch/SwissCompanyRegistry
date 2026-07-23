<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Dto;

use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Values\Uid;

/**
 * Full company record. Fields a given registry does not expose stay
 * null: Zefix has no VAT information, the UID register has no purpose
 * or capital. The untouched registry payload is kept in $raw.
 */
final readonly class Company
{
    public function __construct(
        public Uid $uid,
        public string $name,
        public ?LegalForm $legalForm = null,
        public ?CompanyStatus $status = null,
        /** Commune of the legal seat, e.g. "Lausanne". */
        public ?string $legalSeat = null,
        public ?Canton $canton = null,
        public ?Address $address = null,
        /** Commercial register number, e.g. "CH67010028221". */
        public ?string $chId = null,
        public ?int $ehraId = null,
        /** Registered purpose; only provided by Zefix. */
        public ?string $purpose = null,
        /** Nominal capital as reported by Zefix, e.g. "100000". */
        public ?string $capitalNominal = null,
        public ?string $capitalCurrency = null,
        /** Null when the registry does not state it (e.g. Zefix summaries). */
        public ?bool $inCommercialRegister = null,
        /** Active VAT registration; null when the registry does not state it. */
        public ?bool $vatRegistered = null,
        /** VAT UID when it differs from the enterprise UID (VAT groups). */
        public ?Uid $vatUid = null,
        /** Date of the latest SOGC publication, ISO 8601 (Y-m-d). */
        public ?string $sogcDate = null,
        /** Deletion date, ISO 8601 (Y-m-d), for cancelled companies. */
        public ?string $deletionDate = null,
        /** Link to the cantonal commercial register excerpt. */
        public ?string $cantonalExcerptUrl = null,
        public ?string $zefixUrl = null,
        /** @var array<array-key, mixed> Untouched registry payload for anything not mapped above. */
        public array $raw = [],
    ) {}

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    public function isVatRegistered(): bool
    {
        return $this->vatRegistered === true;
    }

    public function isInCommercialRegister(): bool
    {
        return $this->inCommercialRegister === true;
    }

    public function summary(): CompanySummary
    {
        return new CompanySummary(
            uid: $this->uid,
            name: $this->name,
            legalSeat: $this->legalSeat,
            canton: $this->canton,
            legalForm: $this->legalForm,
            status: $this->status,
            chId: $this->chId,
            ehraId: $this->ehraId,
        );
    }
}
