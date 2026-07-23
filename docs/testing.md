# Testing

`SwissCompany::fake()`, the fluent `CompanyFactory`, the fake's assertions, and the two artisan commands.

## `SwissCompany::fake()`

```php
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Testing\CompanyFactory;

$fake = SwissCompany::fake([
    CompanyFactory::new('CHE-123.456.788')
        ->name('Boulangerie Muster')
        ->canton('VD')
        ->vatRegistered()
        ->make(),
]);

// Your code under test
SwissCompany::suggest('muster');

$fake->assertSearched('muster');
$fake->assertLookedUp('CHE-123.456.788');

// Simulate an outage
$fake->unavailable();
```

`SwissCompany::fake(array $companies = [])` swaps the manager for an in-memory `SwissCompanyRegistryFake`: no network, no cache, every call answered directly from the seeded `Company` list. It returns the fake instance so you can chain assertions or `unavailable()` on it.

The fake's `search()` mimics real routing semantics closely enough for tests: fuzzy queries match anywhere in the name, non-fuzzy queries match from the start, and `canton`, `legalForm` and `activeOnly` filters are honored.

## `CompanyFactory`

Fluent builder for `Company` fixtures. Defaults describe a plausible active sole proprietorship (`CHE-123.456.788`, "Boulangerie Muster", `LegalForm::SoleProprietorship`, `CompanyStatus::Active`, "Lausanne", `Canton::VD`).

```php
CompanyFactory::new('CHE-123.456.788')   // or omit for the default UID
    ->uid('CHE-123.456.788')
    ->name('Boulangerie Muster')
    ->legalForm(LegalForm::SoleProprietorship)  // or null
    ->status(CompanyStatus::Active)             // or null
    ->cancelled()                               // shortcut for ->status(CompanyStatus::Cancelled)
    ->legalSeat('Lausanne')
    ->canton('VD')                              // or Canton::VD, or null
    ->address(new Address(street: 'Rue du Marché', houseNumber: '12', zipCode: '1003', city: 'Lausanne'))
    ->purpose('Boulangerie-pâtisserie artisanale')
    ->vatRegistered()                           // or ->vatRegistered(false), or ->vatRegistered(null)
    ->make();                                   // Company
```

| Method | Sets |
| --- | --- |
| `CompanyFactory::new(string $uid = 'CHE-123.456.788')` | Starts the builder |
| `uid(string $uid)` | The UID (parsed with `Uid::parse()`) |
| `name(string $name)` | The company name |
| `legalForm(?LegalForm $legalForm)` | The legal form |
| `status(?CompanyStatus $status)` | The status |
| `cancelled()` | Shortcut for `status(CompanyStatus::Cancelled)` |
| `legalSeat(?string $legalSeat)` | Commune of the legal seat |
| `canton(Canton\|string\|null $canton)` | The canton |
| `address(?Address $address)` | The address |
| `purpose(?string $purpose)` | The registered purpose |
| `vatRegistered(?bool $vatRegistered = true)` | Whether the UID has an active VAT registration |
| `make()` | Builds the `Company` |

## Assertions

Available on the object returned by `SwissCompany::fake()`:

| Assertion | Passes when |
| --- | --- |
| `assertSearched(string $term)` | At least one `search()`/`suggest()` call's bare name contained `$term` (case-insensitive) |
| `assertNothingSearched()` | No `search()`/`suggest()` call was made at all |
| `assertLookedUp(Uid\|string $uid)` | At least one `find()` call was made for `$uid` |

The fake also records `uidValidations` and `vatValidations` (lists of `Uid`) if you need to assert on `validateUid()`/`validateVatId()` calls directly.

## Simulating an outage

```php
$fake->unavailable();       // every subsequent call behaves as if the registries were down
$fake->unavailable(false);  // back to normal
```

`search()` and `find()` throw `RegistryUnavailableException` while unavailable; `validateUid()` and `validateVatId()` return `Unknown` (never an exception, consistent with real providers).

## Console commands

Both commands are handy to verify credentials and connectivity from a new environment.

### `swiss-company:search`

```bash
php artisan swiss-company:search "muster" --suggest --canton=VD
```

```
+-----------------+--------------------+----------+--------+------------+--------+
| UID             | Name               | Seat     | Canton | Legal form | Status |
+-----------------+--------------------+----------+--------+------------+--------+
| CHE-123.456.788 | Boulangerie Muster | Lausanne | VD     | EI         | ACTIVE |
+-----------------+--------------------+----------+--------+------------+--------+
1 result(s) from the "zefix" provider.
```

Options: `--suggest` (fuzzy match), `--canton=`, `--legal-form=` (eCH-0097 code), `--include-inactive`, `--limit=` (default 20).

### `swiss-company:lookup`

```bash
php artisan swiss-company:lookup CHE-123.456.788
```

```
Boulangerie Muster (CHE-123.456.788)
  Legal form       Sole proprietorship
  Status           ACTIVE
  Seat             Lausanne
  Canton           VD
  Address          Rue du Marché 12, 1003 Lausanne
  Purpose          Boulangerie-pâtisserie artisanale
  CH-ID            CH55010123456
  Cantonal excerpt https://vd.chregister.ch/...
  UID register   valid
  VAT register   active
```

When the UID cannot be parsed, the command fails with an error instead of querying anything. When it parses but carries a wrong check digit, the command warns and queries the registries anyway (some legitimate edge cases exist outside the standard check digit range).
