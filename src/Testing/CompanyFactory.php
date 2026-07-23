<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Testing;

use Kokonut\SwissCompanyRegistry\Dto\Address;
use Kokonut\SwissCompanyRegistry\Dto\Company;
use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Values\Uid;

/**
 * Fluent builder for Company fixtures used with SwissCompany::fake().
 * Defaults describe a plausible active sole proprietorship.
 */
final class CompanyFactory
{
    private Uid $uid;

    private string $name = 'Boulangerie Muster';

    private ?LegalForm $legalForm = LegalForm::SoleProprietorship;

    private ?CompanyStatus $status = CompanyStatus::Active;

    private ?string $legalSeat = 'Lausanne';

    private ?Canton $canton = Canton::VD;

    private ?Address $address = null;

    private ?string $purpose = null;

    private ?bool $vatRegistered = null;

    private function __construct(string $uid)
    {
        $this->uid = Uid::parse($uid);
    }

    public static function new(string $uid = 'CHE-123.456.788'): self
    {
        return new self($uid);
    }

    public function uid(string $uid): self
    {
        $this->uid = Uid::parse($uid);

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function legalForm(?LegalForm $legalForm): self
    {
        $this->legalForm = $legalForm;

        return $this;
    }

    public function status(?CompanyStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function cancelled(): self
    {
        return $this->status(CompanyStatus::Cancelled);
    }

    public function legalSeat(?string $legalSeat): self
    {
        $this->legalSeat = $legalSeat;

        return $this;
    }

    public function canton(Canton|string|null $canton): self
    {
        $this->canton = is_string($canton) ? Canton::from(strtoupper($canton)) : $canton;

        return $this;
    }

    public function address(?Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function purpose(?string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function vatRegistered(?bool $vatRegistered = true): self
    {
        $this->vatRegistered = $vatRegistered;

        return $this;
    }

    public function make(): Company
    {
        return new Company(
            uid: $this->uid,
            name: $this->name,
            legalForm: $this->legalForm,
            status: $this->status,
            legalSeat: $this->legalSeat,
            canton: $this->canton,
            address: $this->address,
            purpose: $this->purpose,
            vatRegistered: $this->vatRegistered,
        );
    }
}
