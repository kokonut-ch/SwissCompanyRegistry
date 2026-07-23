<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Tests\Fixtures;

/**
 * SOAP payloads mirroring real UID register Public Services responses
 * (interface 5.0, public data, captured July 2026).
 */
final class UidRegisterFixtures
{
    public static function validateResult(string $operation, bool $result): string
    {
        $value = $result ? 'true' : 'false';

        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
            ."<{$operation}Response xmlns=\"http://www.uid.admin.ch/xmlns/uid-wse\"><{$operation}Result>{$value}</{$operation}Result></{$operation}Response>"
            .'</s:Body></s:Envelope>';
    }

    public static function searchResponse(): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
            .'<SearchResponse xmlns="http://www.uid.admin.ch/xmlns/uid-wse"><SearchResult>'
            .'<uidEntitySearchResultItem xmlns="http://www.uid.admin.ch/xmlns/uid-wse/5">'
            .self::organisation(
                digits: '319027296',
                name: 'Aubry Carrelage Sàrl',
                legalForm: '0107',
                town: 'Courroux',
                zip: '2822',
                chId: 'CH67040106731',
                ehraId: '1741228',
            )
            .'<rating>99</rating><isHistoryMatch>false</isHistoryMatch>'
            .'</uidEntitySearchResultItem>'
            .'<uidEntitySearchResultItem xmlns="http://www.uid.admin.ch/xmlns/uid-wse/5">'
            .self::organisation(
                digits: '107185562',
                name: 'Boulangerie Aubry',
                legalForm: '0101',
                town: 'Delémont',
                zip: '2800',
                chId: 'CH67010028221',
                ehraId: '677863',
            )
            .'<rating>97</rating><isHistoryMatch>false</isHistoryMatch>'
            .'</uidEntitySearchResultItem>'
            .'</SearchResult></SearchResponse>'
            .'</s:Body></s:Envelope>';
    }

    public static function getByUidResponse(): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
            .'<GetByUIDResponse xmlns="http://www.uid.admin.ch/xmlns/uid-wse"><GetByUIDResult>'
            .'<organisationType xmlns="http://www.uid.admin.ch/xmlns/uid-wse/5">'
            .self::organisation(
                digits: '107185562',
                name: 'Boulangerie Aubry',
                legalForm: '0101',
                town: 'Delémont',
                zip: '2800',
                chId: 'CH67010028221',
                ehraId: '677863',
            )
            .'</organisationType>'
            .'</GetByUIDResult></GetByUIDResponse>'
            .'</s:Body></s:Envelope>';
    }

    public static function emptyGetByUidResponse(): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
            .'<GetByUIDResponse xmlns="http://www.uid.admin.ch/xmlns/uid-wse"><GetByUIDResult>'
            .'</GetByUIDResult></GetByUIDResponse>'
            .'</s:Body></s:Envelope>';
    }

    public static function businessFault(string $error, string $detail): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault>'
            .'<faultcode>s:Client</faultcode><faultstring xml:lang="de-CH">'.$error.'</faultstring>'
            .'<detail><businessFault xmlns="http://www.uid.admin.ch/xmlns/uid-wse">'
            .'<operation xmlns="http://www.uid.admin.ch/xmlns/uid-wse-shared/2">Data validation</operation>'
            .'<error xmlns="http://www.uid.admin.ch/xmlns/uid-wse-shared/2">'.$error.'</error>'
            .'<errorDetail xmlns="http://www.uid.admin.ch/xmlns/uid-wse-shared/2">'.$detail.'</errorDetail>'
            .'</businessFault></detail>'
            .'</s:Fault></s:Body></s:Envelope>';
    }

    private static function organisation(
        string $digits,
        string $name,
        string $legalForm,
        string $town,
        string $zip,
        string $chId,
        string $ehraId,
    ): string {
        return '<organisation>'
            .'<organisation xmlns="http://www.ech.ch/xmlns/eCH-0108/5">'
            .'<organisationIdentification xmlns="http://www.ech.ch/xmlns/eCH-0098/5">'
            .'<uid xmlns="http://www.ech.ch/xmlns/eCH-0097/5">'
            ."<uidOrganisationIdCategorie>CHE</uidOrganisationIdCategorie><uidOrganisationId>{$digits}</uidOrganisationId>"
            .'</uid>'
            .'<OtherOrganisationId xmlns="http://www.ech.ch/xmlns/eCH-0097/5">'
            ."<organisationIdCategory>CH.HR</organisationIdCategory><organisationId>{$chId}</organisationId>"
            .'</OtherOrganisationId>'
            .'<OtherOrganisationId xmlns="http://www.ech.ch/xmlns/eCH-0097/5">'
            ."<organisationIdCategory>CH.EHRAID</organisationIdCategory><organisationId>{$ehraId}</organisationId>"
            .'</OtherOrganisationId>'
            ."<organisationName>{$name}</organisationName>"
            ."<organisationLegalName>{$name}</organisationLegalName>"
            ."<legalForm>{$legalForm}</legalForm>"
            .'</organisationIdentification>'
            .'<address>'
            .'<addressLine1>p.a. Famille Aubry</addressLine1>'
            .'<addressCategory>LEGAL</addressCategory>'
            .'<street>Rue Pierre Péquignat</street><houseNumber>8</houseNumber>'
            ."<town>{$town}</town><swissZipCode>{$zip}</swissZipCode>"
            .'<municipalityId>6711</municipalityId><cantonAbbreviation>JU</cantonAbbreviation>'
            .'<countryIdISO2>CH</countryIdISO2>'
            .'</address>'
            .'</organisation>'
            .'<uidregInformation>'
            .'<uidregStatusEnterpriseDetail>3</uidregStatusEnterpriseDetail>'
            .'<uidregPublicStatus>1</uidregPublicStatus><uidregUidService>false</uidregUidService>'
            .'</uidregInformation>'
            .'<commercialRegisterInformation>'
            .'<commercialRegisterStatus>2</commercialRegisterStatus>'
            .'<commercialRegisterEntryStatus>1</commercialRegisterEntryStatus>'
            .'</commercialRegisterInformation>'
            .'<vatRegisterInformation>'
            .'<vatStatus>2</vatStatus><vatEntryStatus>1</vatEntryStatus><vatEntryDate>2026-04-01</vatEntryDate>'
            .'<uidVat>'
            ."<uidOrganisationIdCategorie>CHE</uidOrganisationIdCategorie><uidOrganisationId>{$digits}</uidOrganisationId>"
            .'</uidVat>'
            .'</vatRegisterInformation>'
            .'</organisation>';
    }
}
