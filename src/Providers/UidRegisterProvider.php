<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Providers;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kokonut\SwissCompanyRegistry\Contracts\FindsCompanies;
use Kokonut\SwissCompanyRegistry\Contracts\SearchesCompanies;
use Kokonut\SwissCompanyRegistry\Contracts\ValidatesUid;
use Kokonut\SwissCompanyRegistry\Contracts\ValidatesVat;
use Kokonut\SwissCompanyRegistry\Dto\Address;
use Kokonut\SwissCompanyRegistry\Dto\Company;
use Kokonut\SwissCompanyRegistry\Dto\CompanySummary;
use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\CompanyStatus;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\ConfigurationException;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidSearchQueryException;
use Kokonut\SwissCompanyRegistry\Exceptions\RegistryUnavailableException;
use Kokonut\SwissCompanyRegistry\Exceptions\UnexpectedResponseException;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\Search\SearchResults;
use Kokonut\SwissCompanyRegistry\Values\Uid;
use Throwable;

/**
 * The Swiss UID register (uid-wse.admin.ch), operated by the Federal
 * Statistical Office, over its SOAP 1.1 Public Services (interface 5.0,
 * eCH-0108). No credentials required.
 *
 * Unlike Zefix it also knows UID entities that are absent from the
 * commercial register, and it is the authoritative source for UID and
 * VAT number validation.
 */
final class UidRegisterProvider implements FindsCompanies, SearchesCompanies, ValidatesUid, ValidatesVat
{
    private const string NS_WSE = 'http://www.uid.admin.ch/xmlns/uid-wse';

    private const string NS_WSE5 = 'http://www.uid.admin.ch/xmlns/uid-wse/5';

    private const string NS_SHARED = 'http://www.uid.admin.ch/xmlns/uid-wse-shared/2';

    private const string NS_ECH0097 = 'http://www.ech.ch/xmlns/eCH-0097/5';

    private const array ENDPOINTS = [
        'production' => 'https://www.uid-wse.admin.ch/V5.0/PublicServices.svc',
        'test' => 'https://www.uid-wse-a.admin.ch/V5.0/PublicServices.svc',
    ];

    public function __construct(
        /** @var array<string, mixed> */
        private readonly array $config = [],
        /** @var array<string, mixed> */
        private readonly array $http = [],
    ) {}

    public function name(): string
    {
        return 'uid-register';
    }

    public function supports(SearchQuery $query): bool
    {
        // The registry-of-commerce office id is a Zefix-internal notion.
        return $query->registryOfCommerceId === null;
    }

    public function search(SearchQuery $query): SearchResults
    {
        $response = $this->call('Search', $this->searchEnvelope($query));

        if ($response->failed()) {
            $this->throwForFailure($response, forSearch: true);
        }

        $xpath = $this->parse($response->body());

        if ($xpath === null) {
            throw UnexpectedResponseException::fromStatus($this->name(), $response->status(), 'The SOAP response could not be parsed.');
        }

        $companies = [];
        $items = $xpath->query("//*[local-name()='uidEntitySearchResultItem']");

        foreach ($items === false ? [] : $items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $summary = $this->mapSummary($xpath, $item);

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
        $response = $this->call('GetByUID', $this->getByUidEnvelope($uid));

        if ($response->failed()) {
            $this->throwForFailure($response, forSearch: false);
        }

        $xpath = $this->parse($response->body());

        if ($xpath === null) {
            throw UnexpectedResponseException::fromStatus($this->name(), $response->status(), 'The SOAP response could not be parsed.');
        }

        $organisations = $xpath->query("//*[local-name()='GetByUIDResult']/*[local-name()='organisationType']");

        $first = $organisations === false ? null : $organisations->item(0);

        if (! $first instanceof DOMElement) {
            return null;
        }

        $company = $this->mapCompany($xpath, $first);

        if ($company === null) {
            Log::debug('Swiss company registry: dropped a result item without a parseable UID.', [
                'provider' => $this->name(),
            ]);
        }

        return $company;
    }

    public function validateUid(Uid $uid): UidValidationResult
    {
        return UidValidationResult::fromBool($this->validateThroughRegister('ValidateUID', 'uid', $uid->value));
    }

    public function validateVatId(Uid $uid): VatValidationResult
    {
        return VatValidationResult::fromBool($this->validateThroughRegister('ValidateVatNumber', 'vatNumber', $uid->value));
    }

    /**
     * Runs one of the boolean validation operations. Returns null (and
     * logs a warning) when the register cannot be consulted, so callers
     * can distinguish "invalid" from "could not verify".
     */
    private function validateThroughRegister(string $operation, string $parameter, string $value): ?bool
    {
        $envelope = $this->envelope(sprintf(
            '<%1$s xmlns="%2$s"><%3$s>%4$s</%3$s></%1$s>',
            $operation,
            self::NS_WSE,
            $parameter,
            $this->xml($value),
        ));

        try {
            $response = $this->call($operation, $envelope);
        } catch (RegistryUnavailableException $exception) {
            Log::warning("UID register unreachable during {$operation}", ['message' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning("UID register {$operation} failed", ['status' => $response->status()]);

            return null;
        }

        if (preg_match('/<(?:\w+:)?'.$operation.'Result[^>]*>\s*(true|false)\s*<\/(?:\w+:)?'.$operation.'Result>/i', $response->body(), $matches) === 1) {
            return strtolower($matches[1]) === 'true';
        }

        Log::warning("UID register returned an unexpected {$operation} payload");

        return null;
    }

    private function call(string $operation, string $envelope): Response
    {
        try {
            return Http::timeout($this->httpInt('timeout', 10))
                ->connectTimeout($this->httpInt('connect_timeout', 5))
                ->retry(
                    $this->httpInt('retries', 2),
                    $this->httpInt('retry_delay', 200),
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->withHeaders([
                    'SOAPAction' => '"'.self::NS_WSE.'/IPublicServices/'.$operation.'"',
                ])
                ->withBody($envelope, 'text/xml; charset=utf-8')
                ->post($this->endpoint());
        } catch (ConnectionException $exception) {
            throw RegistryUnavailableException::unreachable($this->name(), $exception->getMessage());
        }
    }

    private function endpoint(): string
    {
        $endpoint = $this->config['endpoint'] ?? null;

        if (is_string($endpoint) && $endpoint !== '') {
            return $endpoint;
        }

        $environment = $this->config['environment'] ?? 'production';

        return self::ENDPOINTS[$environment]
            ?? throw new ConfigurationException('The UID register environment must be "production" or "test".');
    }

    private function throwForFailure(Response $response, bool $forSearch): never
    {
        $status = $response->status();

        // Business faults arrive as HTTP 500 SOAP faults with a typed
        // <error> element.
        if ($status === 500) {
            $xpath = $this->parse($response->body());

            $error = $xpath === null ? null : $this->text($xpath, null, "//*[local-name()='error']");
            $detail = $xpath === null ? null : $this->text($xpath, null, "//*[local-name()='errorDetail']");

            if ($error === 'Request_limit_exceeded') {
                throw RegistryUnavailableException::rateLimited($this->name());
            }

            if ($error === 'Data_validation_failed' && $forSearch) {
                throw new InvalidSearchQueryException($detail ?? 'The UID register rejected the search parameters.');
            }

            throw UnexpectedResponseException::fromStatus($this->name(), $status, $detail ?? $error);
        }

        if (in_array($status, [502, 503, 504], true)) {
            throw RegistryUnavailableException::maintenance($this->name(), $status);
        }

        if ($status === 429) {
            throw RegistryUnavailableException::rateLimited($this->name());
        }

        throw UnexpectedResponseException::fromStatus($this->name(), $status);
    }

    private function searchEnvelope(SearchQuery $query): string
    {
        $parameters = '<organisationName>'.$this->xml($query->bareName()).'</organisationName>';

        $address = '';

        if ($query->town !== null) {
            $address .= '<town>'.$this->xml($query->town).'</town>';
        }

        if ($query->zipCode !== null) {
            $address .= '<swissZipCode>'.$this->xml($query->zipCode).'</swissZipCode>';
        }

        if ($query->legalSeatId !== null) {
            $address .= '<municipalityId>'.$query->legalSeatId.'</municipalityId>';
        }

        if ($query->canton !== null) {
            $address .= '<cantonAbbreviation>'.$query->canton->value.'</cantonAbbreviation>';
        }

        if ($address !== '') {
            $parameters .= '<address>'.$address.'</address>';
        }

        if ($query->legalForm !== null) {
            $parameters .= '<legalForm>'.$query->legalForm->value.'</legalForm>';
        }

        if ($query->activeOnly) {
            // 3 = definitively registered (active) in the UID register.
            $parameters .= '<uidregInformation><uidregStatusEnterpriseDetail>3</uidregStatusEnterpriseDetail></uidregInformation>';
        }

        $mode = $query->fuzzy ? 'Fuzzy' : 'Auto';
        $limit = $query->limit ?? 0;

        return $this->envelope(sprintf(
            '<Search xmlns="%s"><searchParameters><uidEntitySearchParameters xmlns="%s">%s</uidEntitySearchParameters></searchParameters>'
            .'<config xmlns:sh="%s"><sh:searchMode>%s</sh:searchMode><sh:maxNumberOfRecords>%d</sh:maxNumberOfRecords><sh:searchNameAndAddressHistory>false</sh:searchNameAndAddressHistory></config></Search>',
            self::NS_WSE,
            self::NS_WSE5,
            $parameters,
            self::NS_SHARED,
            $mode,
            $limit,
        ));
    }

    private function getByUidEnvelope(Uid $uid): string
    {
        return $this->envelope(sprintf(
            '<GetByUID xmlns="%s"><uid><uidOrganisationIdCategorie xmlns="%s">CHE</uidOrganisationIdCategorie><uidOrganisationId xmlns="%s">%s</uidOrganisationId></uid></GetByUID>',
            self::NS_WSE,
            self::NS_ECH0097,
            self::NS_ECH0097,
            $uid->digits(),
        ));
    }

    private function envelope(string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .$body
            .'</soap:Body></soap:Envelope>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function parse(string $xml): ?DOMXPath
    {
        if (trim($xml) === '') {
            return null;
        }

        $document = new DOMDocument;

        if (! @$document->loadXML($xml)) {
            return null;
        }

        return new DOMXPath($document);
    }

    private function mapSummary(DOMXPath $xpath, DOMElement $item): ?CompanySummary
    {
        $uid = $this->uidIn($xpath, $item);

        if ($uid === null) {
            return null;
        }

        $rating = $this->text($xpath, $item, "./*[local-name()='rating']");

        return new CompanySummary(
            uid: $uid,
            name: $this->text($xpath, $item, ".//*[local-name()='organisationName']") ?? '',
            legalSeat: $this->text($xpath, $item, ".//*[local-name()='address']/*[local-name()='town']"),
            canton: Canton::tryFrom($this->text($xpath, $item, ".//*[local-name()='cantonAbbreviation']") ?? ''),
            legalForm: LegalForm::tryFrom($this->text($xpath, $item, ".//*[local-name()='legalForm']") ?? ''),
            status: CompanyStatus::fromUidRegisterCode($this->intText($xpath, $item, ".//*[local-name()='uidregStatusEnterpriseDetail']")),
            chId: $this->otherOrganisationId($xpath, $item, 'CH.HR'),
            ehraId: $this->intOrNull($this->otherOrganisationId($xpath, $item, 'CH.EHRAID')),
            rating: $rating === null ? null : (int) $rating,
        );
    }

    private function mapCompany(DOMXPath $xpath, DOMElement $organisation): ?Company
    {
        $uid = $this->uidIn($xpath, $organisation);

        if ($uid === null) {
            return null;
        }

        $town = $this->text($xpath, $organisation, ".//*[local-name()='address']/*[local-name()='town']");

        $vatUidDigits = $this->text($xpath, $organisation, ".//*[local-name()='uidVat']/*[local-name()='uidOrganisationId']");

        return new Company(
            uid: $uid,
            name: $this->text($xpath, $organisation, ".//*[local-name()='organisationName']") ?? '',
            legalForm: LegalForm::tryFrom($this->text($xpath, $organisation, ".//*[local-name()='legalForm']") ?? ''),
            status: CompanyStatus::fromUidRegisterCode($this->intText($xpath, $organisation, ".//*[local-name()='uidregStatusEnterpriseDetail']")),
            legalSeat: $town,
            canton: Canton::tryFrom($this->text($xpath, $organisation, ".//*[local-name()='cantonAbbreviation']") ?? ''),
            address: $this->mapAddress($xpath, $organisation),
            chId: $this->otherOrganisationId($xpath, $organisation, 'CH.HR'),
            ehraId: $this->intOrNull($this->otherOrganisationId($xpath, $organisation, 'CH.EHRAID')),
            inCommercialRegister: match ($this->intText($xpath, $organisation, ".//*[local-name()='commercialRegisterStatus']")) {
                2 => true,
                3 => false,
                default => null,
            },
            vatRegistered: $this->mapVatRegistered($xpath, $organisation),
            vatUid: Uid::tryParse($vatUidDigits),
        );
    }

    private function mapVatRegistered(DOMXPath $xpath, DOMNode $organisation): ?bool
    {
        $vatStatus = $this->intText($xpath, $organisation, ".//*[local-name()='vatStatus']");

        if ($vatStatus === 3) {
            return false;
        }

        if ($vatStatus !== 2) {
            return null;
        }

        $entryStatus = $this->intText($xpath, $organisation, ".//*[local-name()='vatEntryStatus']");

        // 1 = active entry, 2 = terminated. A missing entry status still
        // means the entity is recorded in the VAT register.
        return $entryStatus !== 2;
    }

    private function mapAddress(DOMXPath $xpath, DOMNode $organisation): ?Address
    {
        $node = $xpath->query(".//*[local-name()='address']", $organisation);
        $address = $node === false ? null : $node->item(0);

        if (! $address instanceof DOMElement) {
            return null;
        }

        return new Address(
            careOf: $this->text($xpath, $address, "./*[local-name()='addressLine1']"),
            street: $this->text($xpath, $address, "./*[local-name()='street']"),
            houseNumber: $this->text($xpath, $address, "./*[local-name()='houseNumber']"),
            poBox: $this->text($xpath, $address, "./*[local-name()='postOfficeBoxNumber']"),
            zipCode: $this->text($xpath, $address, "./*[local-name()='swissZipCode']"),
            city: $this->text($xpath, $address, "./*[local-name()='town']"),
            country: $this->text($xpath, $address, "./*[local-name()='countryIdISO2']") ?? 'CH',
        );
    }

    private function uidIn(DOMXPath $xpath, DOMNode $context): ?Uid
    {
        $digits = $this->text($xpath, $context, ".//*[local-name()='uid']/*[local-name()='uidOrganisationId']");
        $category = $this->text($xpath, $context, ".//*[local-name()='uid']/*[local-name()='uidOrganisationIdCategorie']") ?? 'CHE';

        if ($digits === null || $category !== 'CHE') {
            return null;
        }

        return Uid::tryParse('CHE'.$digits);
    }

    private function otherOrganisationId(DOMXPath $xpath, DOMNode $context, string $category): ?string
    {
        $nodes = $xpath->query(".//*[local-name()='OtherOrganisationId']", $context);

        foreach ($nodes === false ? [] : $nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($this->text($xpath, $node, "./*[local-name()='organisationIdCategory']") === $category) {
                return $this->text($xpath, $node, "./*[local-name()='organisationId']");
            }
        }

        return null;
    }

    private function text(DOMXPath $xpath, ?DOMNode $context, string $path): ?string
    {
        $nodes = $xpath->query($path, $context);
        $node = $nodes === false ? null : $nodes->item(0);

        if (! $node instanceof DOMNode) {
            return null;
        }

        $value = trim($node->textContent);

        return $value === '' ? null : $value;
    }

    private function intText(DOMXPath $xpath, ?DOMNode $context, string $path): ?int
    {
        return $this->intOrNull($this->text($xpath, $context, $path));
    }

    private function intOrNull(?string $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function httpInt(string $key, int $default): int
    {
        $value = $this->http[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
