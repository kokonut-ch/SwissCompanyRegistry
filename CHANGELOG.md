# Changelog

All notable changes to `laravel-swiss-company-registry` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Zefix provider (Central Business Name Index, REST): company search with canton,
  legal form, commune and registry-office filters, wildcard suggestions, full
  company details by UID.
- UID register provider (Federal Statistical Office, SOAP Public Services):
  company search (including entities absent from the commercial register),
  details by UID, `ValidateUID` and `ValidateVatNumber`.
- Capability-based provider routing with configurable fallback chain.
- `Uid` value object with eCH-0097 check-digit validation and official
  formatting (`CHE-123.456.789`, localized VAT suffixes TVA/MWST/IVA).
- Typed DTOs (`Company`, `CompanySummary`, `Address`) and enums (`Canton`,
  `LegalForm`, `CompanyStatus`) with fr/de/it/en labels.
- Tri-state validation results (`UidValidationResult`, `VatValidationResult`).
- Response caching with configurable store and TTL.
- Laravel validation rules `ValidUid`, `RegisteredUid`, `ActiveVatNumber`.
- Testing fake (`SwissCompany::fake()`) with fluent `CompanyFactory`.
- Artisan commands `swiss-company:search` and `swiss-company:lookup`.

### Changed

- Provider fallback now also triggers on `UnexpectedResponseException`, not only on
  `RegistryUnavailableException`, and logs a warning naming the provider, the operation
  and the exception message before trying the next one.
- Empty search results are no longer cached, so a transient empty response does not get
  served back for the rest of the cache TTL.

### Fixed

- `RegisteredUid`, `ActiveVatNumber` and `swiss-company:lookup` no longer crash when no
  configured provider offers UID or VAT validation; they degrade to the same "could not
  verify" outcome as an unreachable registry instead of throwing.
- Zefix HTTP 401/403 responses are now reported as a `ConfigurationException` naming
  `ZEFIX_USERNAME` / `ZEFIX_PASSWORD`, instead of an `UnexpectedResponseException`.
- UID register HTTP 429 responses are now reported as `RegistryUnavailableException`
  (rate limiting), matching the existing Zefix behavior.
- An unknown cache store configured under `swiss-company-registry.cache.store` is now
  reported as a `ConfigurationException` instead of letting Laravel's own
  `InvalidArgumentException` escape.
