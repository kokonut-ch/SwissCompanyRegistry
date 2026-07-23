<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Dto;

final readonly class Address
{
    public function __construct(
        /** "c/o" line, e.g. "p.a. Famille Muster". */
        public ?string $careOf = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
        /** Additional address line, e.g. "c/o" or building. */
        public ?string $addon = null,
        public ?string $poBox = null,
        public ?string $zipCode = null,
        public ?string $city = null,
        /** ISO 3166-1 alpha-2, e.g. "CH". */
        public ?string $country = 'CH',
    ) {}

    /** "Rue du Marché 12" or null when no street is known. */
    public function streetLine(): ?string
    {
        if ($this->street === null) {
            return null;
        }

        return trim($this->street.' '.($this->houseNumber ?? ''));
    }

    /** "1003 Lausanne" or null when no city is known. */
    public function cityLine(): ?string
    {
        if ($this->city === null) {
            return null;
        }

        return trim(($this->zipCode ?? '').' '.$this->city);
    }

    /** "Rue du Marché 12, 1003 Lausanne". */
    public function oneLine(): ?string
    {
        $parts = array_filter([$this->streetLine(), $this->cityLine()]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** The raw post office box, e.g. "Case postale 123", or null when unknown. */
    public function poBoxLine(): ?string
    {
        return $this->poBox;
    }

    /**
     * The address broken down into printable lines, in postal order:
     * care-of, street, post office box, then zip and city.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->careOf,
            $this->streetLine(),
            $this->poBox,
            $this->cityLine(),
        ]));
    }

    /** The address as a single, newline-joined block, or null when there is nothing to show. */
    public function block(): ?string
    {
        $lines = $this->lines();

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @return array{careOf: ?string, street: ?string, houseNumber: ?string, addon: ?string, poBox: ?string, zipCode: ?string, city: ?string, country: ?string}
     */
    public function toArray(): array
    {
        return [
            'careOf' => $this->careOf,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'addon' => $this->addon,
            'poBox' => $this->poBox,
            'zipCode' => $this->zipCode,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}
