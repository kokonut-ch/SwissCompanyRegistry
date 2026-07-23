<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kokonut\SwissCompanyRegistry\Contracts\FindsCompanies;
use Kokonut\SwissCompanyRegistry\Contracts\SearchesCompanies;
use Kokonut\SwissCompanyRegistry\Dto\Address;
use Kokonut\SwissCompanyRegistry\Dto\Company;
use Kokonut\SwissCompanyRegistry\Dto\CompanySummary;
use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Exceptions\ConfigurationException;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Exceptions\TooManyResultsException;
use Kokonut\SwissCompanyRegistry\Exceptions\UnexpectedResponseException;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Search\SearchResults;
use Kokonut\SwissCompanyRegistry\Support\DisplayLocale;
use Kokonut\SwissCompanyRegistry\Values\Uid;
use Throwable;

/**
 * Zefix, the Central Business Name Index (zefix.admin.ch), over its
 * public REST API. Covers every entity recorded in the cantonal
 * commercial registers; requires free credentials issued by the Federal
 * Office of Justice.
 *
 * Zefix name search matches from the beginning of the name unless
 * wildcards are used; fuzzy queries are therefore sent as "*term*".
 */
final class ZefixProvider implements FindsCompanies, SearchesCompanies
{
    private const array ENDPOINTS = [
        'production' => 'https://www.zefix.admin.ch/ZefixPublicREST/api/v1',
        'test' => 'https://www.zefixintg.admin.ch/ZefixPublicREST/api/v1',
    ];

    public function __construct(
        /** @var array<string, mixed> */
        private readonly array $config = [],
        /** @var array<string, mixed> */
        private readonly array $http = [],
    ) {}

    public function name(): string
    {
        return 'zefix';
    }

    public function supports(SearchQuery $query): bool
    {
        // Zefix cannot filter on town or zip code, and rejects terms
        // shorter than 3 characters.
        return $query->town === null
            && $query->zipCode === null
            && mb_strlen($query->bareName()) >= 3;
    }

    public function search(SearchQuery $query): SearchResults
    {
        $payload = array_filter([
            'name' => $query->effectiveName(),
            'activeOnly' => $query->activeOnly,
            'canton' => $query->canton?->value,
            'legalFormUid' => $query->legalForm?->value,
            'legalSeatId' => $query->legalSeatId,
            'registryOfCommerceId' => $query->registryOfCommerceId,
        ], fn (mixed $value): bool => $value !== null);

        $response = $this->request(fn (PendingRequest $client): Response => $client->post('/company/search', $payload));

        if ($response->failed()) {
            $this->throwForFailure($response);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw UnexpectedResponseException::fromStatus($this->name(), $response->status(), 'Expected a JSON array of companies.');
        }

        $companies = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $summary = $this->mapSummary($item);

            if ($summary === null) {
                Log::debug('Swiss company registry: dropped a result item without a parseable UID.', [
                    'provider' => $this->name(),
                ]);

                continue;
            }

            $companies[] = $summary;
        }

        if ($query->limit !== null) {
            $companies = array_slice($companies, 0, $query->limit);
        }

        return new SearchResults($companies, $this->name());
    }

    public function find(Uid $uid): ?Company
    {
        $response = $this->request(fn (PendingRequest $client): Response => $client->get('/company/uid/'.$uid->value));

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            $this->throwForFailure($response);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw UnexpectedResponseException::fromStatus($this->name(), $response->status(), 'Expected a JSON array of companies.');
        }

        $first = $data[0] ?? null;

        if (! is_array($first)) {
            return null;
        }

        $company = $this->mapCompany($first);

        if ($company === null) {
            Log::debug('Swiss company registry: dropped a result item without a parseable UID.', [
                'provider' => $this->name(),
            ]);
        }

        return $company;
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     */
    private function request(callable $send): Response
    {
        try {
            return $send($this->client());
        } catch (ConnectionException $exception) {
            throw RegistryUnavailableException::unreachable($this->name(), $exception->getMessage());
        }
    }

    private function client(): PendingRequest
    {
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            throw new ConfigurationException(
                'Zefix credentials are missing. Set ZEFIX_USERNAME and ZEFIX_PASSWORD; free registration at https://www.zefix.admin.ch.',
            );
        }

        return Http::baseUrl($this->baseUrl())
            ->withBasicAuth($username, $password)
            ->acceptJson()
            ->timeout($this->httpInt('timeout', 10))
            ->connectTimeout($this->httpInt('connect_timeout', 5))
            ->retry(
                $this->httpInt('retries', 2),
                $this->httpInt('retry_delay', 200),
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
    }

    private function baseUrl(): string
    {
        $baseUrl = $this->config['base_url'] ?? null;

        if (is_string($baseUrl) && $baseUrl !== '') {
            return $baseUrl;
        }

        $environment = $this->config['environment'] ?? 'production';

        return self::ENDPOINTS[$environment]
            ?? throw new ConfigurationException('The Zefix environment must be "production" or "test".');
    }

    private function throwForFailure(Response $response): never
    {
        $status = $response->status();

        if (in_array($status, [502, 503, 504], true)) {
            throw RegistryUnavailableException::maintenance($this->name(), $status);
        }

        if ($status === 429) {
            throw RegistryUnavailableException::rateLimited($this->name());
        }

        if ($status === 401 || $status === 403) {
            throw new ConfigurationException("Zefix rejected the credentials (HTTP {$status}). Check ZEFIX_USERNAME and ZEFIX_PASSWORD.");
        }

        $errorType = $response->json('error.type');

        if ($errorType === 'RESULTLIST_TO_LARGE') {
            throw new TooManyResultsException('Zefix refused the search because it matches too many companies. Narrow the term or add filters.');
        }

        $message = $response->json('error.message');

        throw UnexpectedResponseException::fromStatus($this->name(), $status, is_string($message) ? $message : null);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function mapSummary(array $data): ?CompanySummary
    {
        $uid = Uid::tryParse(self::str($data, 'uid'));

        if ($uid === null) {
            return null;
        }

        return new CompanySummary(
            uid: $uid,
            name: self::str($data, 'name') ?? '',
            legalSeat: self::str($data, 'legalSeat'),
            canton: null,
            legalForm: $this->mapLegalForm($data),
            status: CompanyStatus::tryFrom(self::str($data, 'status') ?? ''),
            chId: self::str($data, 'chid'),
            ehraId: self::int($data, 'ehraid'),
            raw: $data,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function mapCompany(array $data): ?Company
    {
        $uid = Uid::tryParse(self::str($data, 'uid'));

        if ($uid === null) {
            return null;
        }

        $zefixUrls = $data['zefixDetailWeb'] ?? null;

        return new Company(
            uid: $uid,
            name: self::str($data, 'name') ?? '',
            legalForm: $this->mapLegalForm($data),
            status: CompanyStatus::tryFrom(self::str($data, 'status') ?? ''),
            legalSeat: self::str($data, 'legalSeat'),
            canton: Canton::tryFrom(self::str($data, 'canton') ?? ''),
            address: $this->mapAddress($data['address'] ?? null),
            chId: self::str($data, 'chid'),
            ehraId: self::int($data, 'ehraid'),
            purpose: self::str($data, 'purpose'),
            capitalNominal: self::str($data, 'capitalNominal'),
            capitalCurrency: self::str($data, 'capitalCurrency'),
            inCommercialRegister: true,
            vatRegistered: null,
            sogcDate: self::str($data, 'sogcDate'),
            deletionDate: self::str($data, 'deletionDate'),
            cantonalExcerptUrl: self::str($data, 'cantonalExcerptWeb'),
            zefixUrl: is_array($zefixUrls) ? $this->zefixDetailUrl($zefixUrls) : null,
            raw: $data,
        );
    }

    /**
     * Picks the zefixDetailWeb URL for the resolved display locale,
     * falling back to English, then to any other available entry.
     *
     * @param  array<array-key, mixed>  $urls
     */
    private function zefixDetailUrl(array $urls): ?string
    {
        return self::str($urls, DisplayLocale::resolve(null))
            ?? self::str($urls, 'en')
            ?? self::firstStr($urls);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function mapLegalForm(array $data): ?LegalForm
    {
        $legalForm = $data['legalForm'] ?? null;

        if (! is_array($legalForm)) {
            return null;
        }

        return LegalForm::tryFrom(self::str($legalForm, 'uid') ?? '');
    }

    private function mapAddress(mixed $data): ?Address
    {
        if (! is_array($data)) {
            return null;
        }

        return new Address(
            careOf: self::str($data, 'careOf'),
            street: self::str($data, 'street'),
            houseNumber: self::str($data, 'houseNumber'),
            addon: self::str($data, 'addon'),
            poBox: self::str($data, 'poBox'),
            zipCode: self::str($data, 'swissZipCode'),
            city: self::str($data, 'city'),
            country: 'CH',
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function firstStr(array $data): ?string
    {
        foreach ($data as $value) {
            if (is_int($value) || is_float($value)) {
                $value = (string) $value;
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }

    private function httpInt(string $key, int $default): int
    {
        $value = $this->http[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
