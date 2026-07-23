# Company data

The `Company` and `CompanySummary` DTOs, the `Address` value object, and the `Canton` / `LegalForm` / `CompanyStatus` enums.

## `Company`

```php
$company = SwissCompany::find('CHE-123.456.788');   // Company|null

$company->address?->oneLine();     // "Rue du Marché 12, 1003 Lausanne"
$company->canton;                  // Canton::VD
$company->purpose;                 // from Zefix
$company->isInCommercialRegister();
$company->isVatRegistered();       // from the UID register
$company->cantonalExcerptUrl;      // official excerpt
```

Full company record, `final readonly`. Fields a given registry does not expose stay `null`: Zefix has no VAT information, the UID register has no purpose or capital. The untouched registry payload is kept in `$raw`.

| Field | Type | Notes |
| --- | --- | --- |
| `uid` | `Uid` | See [docs/validation.md](validation.md) |
| `name` | `string` | |
| `legalForm` | `?LegalForm` | |
| `status` | `?CompanyStatus` | |
| `legalSeat` | `?string` | Commune of the legal seat, e.g. `"Lausanne"` |
| `canton` | `?Canton` | |
| `address` | `?Address` | See [below](#address) |
| `chId` | `?string` | Commercial register number, e.g. `"CH67010028221"` |
| `ehraId` | `?int` | |
| `purpose` | `?string` | Registered purpose; only provided by Zefix |
| `capitalNominal` | `?string` | Nominal capital as reported by Zefix, e.g. `"100000"` |
| `capitalCurrency` | `?string` | |
| `inCommercialRegister` | `?bool` | Null when the registry does not state it (e.g. Zefix summaries) |
| `vatRegistered` | `?bool` | Null when the registry does not state it |
| `vatUid` | `?Uid` | VAT UID when it differs from the enterprise UID (VAT groups) |
| `sogcDate` | `?string` | Date of the latest SOGC publication, ISO 8601 (`Y-m-d`) |
| `deletionDate` | `?string` | Deletion date, ISO 8601 (`Y-m-d`), for cancelled companies |
| `cantonalExcerptUrl` | `?string` | Link to the cantonal commercial register excerpt |
| `zefixUrl` | `?string` | |
| `raw` | `array` | Untouched registry payload for anything not mapped above |

Helper methods:

| Method | Returns true when |
| --- | --- |
| `isActive()` | `status === CompanyStatus::Active` |
| `isVatRegistered()` | `vatRegistered === true` |
| `isInCommercialRegister()` | `inCommercialRegister === true` |

`$company->summary(): CompanySummary` projects a `Company` down to a `CompanySummary`, the same shape returned by search results.

## `CompanySummary`

One search hit, `final readonly`, deliberately lightweight. Fetch the full record with `SwissCompany::find($summary->uid)` when more than the essentials are needed. Fields a registry does not expose in its result list stay `null`.

| Field | Type | Notes |
| --- | --- | --- |
| `uid` | `Uid` | |
| `name` | `string` | |
| `legalSeat` | `?string` | Commune of the legal seat, e.g. `"Lausanne"` |
| `canton` | `?Canton` | |
| `legalForm` | `?LegalForm` | |
| `status` | `?CompanyStatus` | |
| `chId` | `?string` | Commercial register number |
| `ehraId` | `?int` | |
| `rating` | `?int` | Match confidence 0-100, only set by registries that score hits |
| `raw` | `array` | Untouched registry payload |

`isActive()` returns true when `status === CompanyStatus::Active`.

## `Address`

`final readonly`, with a line-by-line API for printing postal addresses.

| Field | Type | Notes |
| --- | --- | --- |
| `careOf` | `?string` | The "c/o" line, e.g. `"p.a. Famille Muster"` |
| `street` | `?string` | |
| `houseNumber` | `?string` | |
| `addon` | `?string` | Additional address line, e.g. building |
| `poBox` | `?string` | |
| `zipCode` | `?string` | |
| `city` | `?string` | |
| `country` | `?string` | ISO 3166-1 alpha-2, defaults to `"CH"` |

```php
$address->streetLine();  // "Rue du Marché 12" or null
$address->cityLine();    // "1003 Lausanne" or null
$address->poBoxLine();   // the raw post office box, or null
$address->oneLine();     // "Rue du Marché 12, 1003 Lausanne"
$address->lines();       // ["p.a. Famille Muster", "Rue du Marché 12", ..., "1003 Lausanne"]
$address->block();       // lines() joined with "\n", or null when there is nothing to show
$address->toArray();     // ['careOf' => ..., 'street' => ..., ..., 'country' => ...]
```

| Method | Returns |
| --- | --- |
| `streetLine()` | `street` and `houseNumber` combined, e.g. `"Rue du Marché 12"`, or `null` when no street is known |
| `cityLine()` | `zipCode` and `city` combined, e.g. `"1003 Lausanne"`, or `null` when no city is known |
| `poBoxLine()` | The raw post office box, or `null` when unknown |
| `oneLine()` | `streetLine()` and `cityLine()` joined with `", "`, e.g. `"Rue du Marché 12, 1003 Lausanne"` |
| `lines()` | `list<string>`: every non-empty line, in postal order: `careOf`, street line, PO box, then city line |
| `block()` | `lines()` joined with `"\n"` into a single printable string, or `null` when there is nothing to show |
| `toArray()` | `array{careOf, street, houseNumber, addon, poBox, zipCode, city, country}` |

Use `lines()` or `block()` when rendering a full postal address (letters, PDFs); use `oneLine()` for compact display (tables, lists).

## `Canton`

Backed by the official two-letter abbreviation used by both Zefix and the UID register (all 26 cantons).

```php
$company->canton?->label('fr');   // "Jura"
$company->canton?->label();       // "Jura" (follows the display locale)
```

With no argument, `label()` follows the configured display locale (`config('swiss-company-registry.locale')`), which itself defaults to the application locale; see [docs/configuration.md](configuration.md#display-language).

| Method | Returns |
| --- | --- |
| `label(?string $locale = null)` | Full name in `'de'`, `'fr'`, `'it'` or `'en'` (falls back to English) |
| `labels()` | `array{de, fr, it, en}` of the full name |

## `LegalForm`

Backed by the public eCH-0097 code. Labels reproduce the official wording served by the Zefix `LegalForm` endpoint in the four supported languages.

```php
$company->legalForm?->label('fr');       // "Entreprise individuelle"
$company->legalForm?->shortLabel('fr');  // "EI"
```

| Method | Returns |
| --- | --- |
| `label(?string $locale = null)` | Full official name; with no argument, follows the display locale (falls back to English) |
| `shortLabel(?string $locale = null)` | Short form, e.g. `"Ltd"` / `"SA"` / `"AG"` for `Corporation`; with no argument, follows the display locale (falls back to English) |
| `labels()` | `array{de, fr, it, en}` of the full name |
| `shortLabels()` | `array{de, fr, it, en}` of the short form |
| `zefixId()` | The internal numeric id used by the Zefix REST API |
| `LegalForm::fromZefixId(?int $id)` | Reverse lookup, or `null` for an unknown id |

## `CompanyStatus`

```php
enum CompanyStatus: string
{
    case Active = 'ACTIVE';
    case Cancelled = 'CANCELLED';
}
```

`CompanyStatus::fromUidRegisterCode(?int $code)` maps the eCH-0108 `uidregStatusEnterpriseDetail` code used by the UID register. Only unambiguous codes are mapped (3 → Active, 6/7 → Cancelled); everything else (provisional, in mutation, ...) yields `null`.
