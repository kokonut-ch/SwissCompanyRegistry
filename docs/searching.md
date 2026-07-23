# Searching

`search()` and `suggest()`, the full `SearchQuery` filter API, provider auto-routing, and the `SearchResults` collection.

## `search()` vs `suggest()`

```php
use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;

// Simple: matches from the beginning of the name (registry semantics)
$results = SwissCompany::search('Boulangerie Muster');

// Autocomplete: matches anywhere in the name, always fuzzy
$results = SwissCompany::suggest('muster', limit: 10, canton: 'VD');
```

`search()` accepts either a plain string (matched from the beginning of the name) or a [`SearchQuery`](#the-searchquery-api) for full control over filters. `suggest()` is a shortcut for a fuzzy, limited query: `suggest($term, $limit, $canton)` is equivalent to `search(SearchQuery::make($term)->fuzzy()->canton($canton)->limit($limit))`, capped to `$limit` results afterwards.

## The `SearchQuery` API

```php
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;

$query = SearchQuery::make('muster')
    ->fuzzy()                 // wildcard / similarity search: match anywhere in the name
    ->canton('VD')             // or Canton::VD
    ->legalForm('0101')        // or LegalForm::SoleProprietorship
    ->includeInactive()        // cancelled companies too
    ->limit(20);
```

| Method | Effect |
| --- | --- |
| `SearchQuery::make(string $name)` | Creates the query; throws `InvalidSearchQueryException` on an empty name |
| `->fuzzy(bool $fuzzy = true)` | Match anywhere in the name instead of only from its beginning. This is what turns `"muster"` into a hit for "Boulangerie Muster" |
| `->includeInactive(bool $include = true)` | Include cancelled companies (default: active only) |
| `->canton(Canton\|string\|null $canton)` | Filter by canton, e.g. `'JU'` or `Canton::VD`. Cannot be combined with `legalSeatId()` or `registryOfCommerceId()` |
| `->legalForm(LegalForm\|string\|null $legalForm)` | Filter by eCH-0097 legal form code, e.g. `'0106'` or `LegalForm::Corporation` |
| `->town(?string $town)` | Commune of the legal seat. Supported by the UID register only |
| `->zipCode(?string $zipCode)` | Swiss zip code. Supported by the UID register only |
| `->legalSeatId(?int $bfsCommuneId)` | BFS commune number. Supported by Zefix only. Cannot be combined with `canton()` |
| `->registryOfCommerceId(?int $officeId)` | Cantonal registry-of-commerce office id. Supported by Zefix only. Cannot be combined with `canton()` |
| `->limit(?int $limit)` | Maximum number of rows |
| `->bareName(): string` | The term stripped of any wildcards the caller may have typed |
| `->effectiveName(): string` | The name as sent to wildcard-based registries such as Zefix (`*term*` when fuzzy) |
| `->fingerprint(): string` | Stable identity of the query, used for cache keys |

Combining incompatible filters (e.g. `canton()` with `legalSeatId()`) throws `InvalidSearchQueryException` immediately, before any provider is consulted.

## Provider auto-routing

Provider-specific filters are routed automatically:

- A query with `->town()` or `->zipCode()` goes to the UID register (Zefix cannot filter on those).
- A query with `->registryOfCommerceId()` goes to Zefix.
- A query with `->legalSeatId()` goes to Zefix.

Each call is routed to the first provider in the chain (default first, see [docs/providers.md](providers.md)) whose `supports()` accepts the exact combination of filters in use; on an outage, the next capable provider is tried. When no configured provider can honor the combination, `InvalidSearchQueryException` explains why. See the [capability matrix](providers.md#capability-matrix) for exactly which filters each provider supports.

## `SearchResults`

```php
foreach ($results as $company) {
    $company->uid->format();            // "CHE-123.456.788"
    $company->name;                     // "Boulangerie Muster"
    $company->legalSeat;                // "Lausanne"
    $company->legalForm?->label('fr');  // "Entreprise individuelle"
    $company->isActive();               // true
}
```

`SearchResults` is `Countable` and `IteratorAggregate` over a list of [`CompanySummary`](company-data.md#companysummary) objects.

| Method | Returns |
| --- | --- |
| `all()` | `list<CompanySummary>`, every match |
| `first()` | The first `CompanySummary`, or `null` when empty |
| `isEmpty()` | Whether there are no matches |
| `count()` | Number of matches |
| `take(int $limit)` | A new `SearchResults` truncated to `$limit` |
| `toSelectOptions(?callable $label = null)` | `array<string, string>` keyed by formatted UID, ready for a select input |

```php
$results->toSelectOptions();
// ["CHE-123.456.788" => "Boulangerie Muster (Lausanne)", ...]
```

The default label is `"{name} ({legalSeat})"`, or just the name when the legal seat is unknown. Pass a callable to customize it.

`SearchResults` also exposes the `provider` that produced it (the machine key, e.g. `"zefix"`), useful for diagnostics or the `toSelectOptions()` label.

## `TooManyResultsException`

Some providers refuse to answer when a term is too broad (typically an unfiltered one- or two-letter query). Narrow the search term or add filters (`canton()`, `legalForm()`, `limit()`) to avoid it; catching it and asking the user to be more specific is a reasonable UX approach.
