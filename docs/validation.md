# Validation

The `SwissUid` facade, the underlying `Uid` value object, the tri-state `UidValidationResult` / `VatValidationResult`, and the three Laravel validation rules.

## The `SwissUid` facade

Everything offline first: `SwissUid` parses lenient input and checks the eCH-0097 check digit without any network call, so application code never has to import the value object class:

```php
use Kokonut\SwissCompanyRegistry\Facades\SwissUid;
use Kokonut\SwissCompanyRegistry\Enums\VatSuffix;

SwissUid::isValid('CHE-109.322.551');             // format + check digit, offline
$uid = SwissUid::parse('che 109 322 551 iva');    // lenient parsing
$uid->format();                                   // "CHE-109.322.551"
SwissUid::formatVat($uid, VatSuffix::forLocale('de')); // "CHE-109.322.551 MWST"
```

| Method | Behavior |
| --- | --- |
| `SwissUid::parse(Uid\|string\|null $value)` | Parses lenient input; throws `InvalidUidException` when no plausible UID is found |
| `SwissUid::tryParse(Uid\|string\|null $value)` | Same, returns `null` instead of throwing |
| `SwissUid::isValid(Uid\|string\|null $value)` | Parseable and carrying a correct eCH-0097 check digit |
| `SwissUid::format(Uid\|string\|null $value)` | `format()` after `tryParse()`, or `null` when unparseable |
| `SwissUid::formatVat(Uid\|string\|null $value, VatSuffix $suffix = VatSuffix::TVA)` | `formatVat()` after `tryParse()`, or `null` when unparseable |

Like `SwissCompany`, the `SwissUid` alias is auto-registered by Laravel's package discovery (see `extra.laravel.aliases` in composer.json), so `\SwissUid::isValid(...)` works out of the box, without an import.

Parsing is lenient about separators and tolerates the TVA/MWST/IVA suffixes, as well as bare 9-digit strings (legacy inputs sometimes carry the 9 digits without the `CHE` prefix, e.g. the old `"TVA-123.456.789"` mask).

`VatSuffix::forLocale(?string $locale)` picks the official language suffix (`MWST` for `de`, `IVA` for `it`, `TVA` otherwise; there is no English variant).

## The `Uid` value object

`SwissUid::parse()` and `SwissUid::tryParse()` return an immutable `Kokonut\SwissCompanyRegistry\Values\Uid` instance, the type carried by DTOs (`Company->uid`, `Company->vatUid`). A parsed instance always holds the canonical `"CHE123456789"` form, and exposes the same API as static methods, for code that already holds a `Uid` and does not need the facade:

| Method | Behavior |
| --- | --- |
| `Uid::parse(self\|string\|null $value)` | Parses lenient input; throws `InvalidUidException` when no plausible UID is found |
| `Uid::tryParse(self\|string\|null $value)` | Same, returns `null` instead of throwing |
| `Uid::isValid(self\|string\|null $value)` | Parseable and carrying a correct eCH-0097 check digit |
| `digits()` | The 9 digits without the `CHE` prefix |
| `hasValidCheckDigit()` | The modulo-11 eCH-0097 check digit (weights 5-4-3-2-7-6-5-4) is correct |
| `format()` | Official display format: `"CHE-123.456.789"` |
| `formatVat(VatSuffix $suffix = VatSuffix::TVA)` | Official VAT number format: `"CHE-123.456.789 TVA"` (or `MWST`/`IVA`) |
| `equals(self\|string\|null $other)` | Value equality after parsing `$other` |

## Online validation: tri-state results

Online validation returns a tri-state result so an unreachable registry is never mistaken for a rejection:

```php
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;

$result = SwissCompany::validateUid('CHE-109.322.551');

match ($result) {
    UidValidationResult::Valid => 'registered (possibly dissolved)',
    UidValidationResult::Invalid => 'not in the UID register',
    UidValidationResult::Unknown => 'register unreachable, could not verify',
};

SwissCompany::validateVatId('CHE-109.322.551');  // Active | Inactive | Unknown
```

`UidValidationResult` (`Valid` / `Invalid` / `Unknown`): a UID is `Valid` when it is assigned to an entity, even one that has since been dissolved. `Unknown` means the register could not be consulted, never a rejection.

`VatValidationResult` (`Active` / `Inactive` / `Unknown`): the register only reports `Active` for numbers with a currently active VAT registration; `Inactive` covers both unknown numbers and terminated registrations. `Unknown` again means the register could not be consulted.

Both enums expose:

| Method | Meaning |
| --- | --- |
| `isValid()` / `isActive()` | True only for the positive case |
| `isKnown()` | False only when the register could not be consulted |
| `::fromBool(?bool $value)` | `true → Valid/Active`, `false → Invalid/Inactive`, `null → Unknown` |

`SwissCompany::validateUid()` and `SwissCompany::validateVatId()` never throw on outages; unparseable input yields `Invalid`/`Inactive` immediately (no network call), and an unreachable registry yields `Unknown`. See [docs/providers.md](providers.md#errors) for how this compares to the exceptions thrown elsewhere in the package.

## Validation rules

```php
use Kokonut\SwissCompanyRegistry\Rules\ValidUid;
use Kokonut\SwissCompanyRegistry\Rules\RegisteredUid;
use Kokonut\SwissCompanyRegistry\Rules\ActiveVatNumber;

$request->validate([
    'uid' => ['required', new ValidUid],         // offline: format + check digit
    'uid' => ['required', new RegisteredUid],     // online: exists in the UID register
    'vat' => ['required', new ActiveVatNumber],   // online: active VAT registration
]);
```

| Rule | Checks | Network |
| --- | --- | --- |
| `ValidUid` | Parseable and carrying a correct eCH-0097 check digit | No |
| `RegisteredUid` | Valid format, and the UID exists in the UID register | Yes |
| `ActiveVatNumber` | Valid format, and belongs to an ACTIVE Swiss VAT registration | Yes |

For `RegisteredUid` and `ActiveVatNumber`, when the register cannot be reached the rule passes by default (the format has already been checked offline), unless strict mode is requested:

```php
// Strict variants fail instead of passing when the register is unreachable
RegisteredUid::strict();
ActiveVatNumber::strict();
```
