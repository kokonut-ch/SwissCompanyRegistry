<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Enums;

use Kokonut\SwissCompanyRegistry\Support\DisplayLocale;

/**
 * Swiss legal forms, backed by their public eCH-0097 code. Labels and
 * short labels reproduce the official wording served by the Zefix
 * LegalForm endpoint in the four supported languages.
 */
enum LegalForm: string
{
    case SoleProprietorship = '0101';
    case GeneralPartnership = '0103';
    case LimitedPartnership = '0104';
    case PartnershipLimitedByShares = '0105';
    case Corporation = '0106';
    case LimitedLiabilityCompany = '0107';
    case Cooperative = '0108';
    case Association = '0109';
    case Foundation = '0110';
    case ForeignBranch = '0111';
    case SpecialLegalForm = '0113';
    case CollectiveInvestmentLimitedPartnership = '0114';
    case Sicav = '0115';
    case Sicaf = '0116';
    case PublicSectorInstitution = '0117';
    case NonCommercialPowerOfAttorney = '0118';
    case OwnershipInUndividedShares = '0119';
    case Branch = '0151';

    /**
     * @param  'de'|'fr'|'it'|'en'|string|null  $locale  Defaults to the
     *                                                   configured display locale (see `DisplayLocale::resolve()`), itself
     *                                                   falling back to English.
     */
    public function label(?string $locale = null): string
    {
        $labels = $this->labels();

        return $labels[DisplayLocale::resolve($locale)] ?? $labels['en'];
    }

    /**
     * @param  'de'|'fr'|'it'|'en'|string|null  $locale  Defaults to the
     *                                                   configured display locale (see `DisplayLocale::resolve()`), itself
     *                                                   falling back to English.
     */
    public function shortLabel(?string $locale = null): string
    {
        $labels = $this->shortLabels();

        return $labels[DisplayLocale::resolve($locale)] ?? $labels['en'];
    }

    /** @return array{de: string, fr: string, it: string, en: string} */
    public function labels(): array
    {
        return match ($this) {
            self::SoleProprietorship => ['de' => 'Einzelunternehmen', 'fr' => 'Entreprise individuelle', 'it' => 'Ditta individuale', 'en' => 'Sole proprietorship'],
            self::GeneralPartnership => ['de' => 'Kollektivgesellschaft', 'fr' => 'Société en nom collectif', 'it' => 'Società in nome collettivo', 'en' => 'General Partnership'],
            self::LimitedPartnership => ['de' => 'Kommanditgesellschaft', 'fr' => 'Société en commandite', 'it' => 'Società in accomandita', 'en' => 'Limited Partnership'],
            self::PartnershipLimitedByShares => ['de' => 'Kommanditaktiengesellschaft', 'fr' => 'Société en commandite par actions', 'it' => 'Società in accomandita per azioni', 'en' => 'Corporation with unlimited partners'],
            self::Corporation => ['de' => 'Aktiengesellschaft', 'fr' => 'Société anonyme', 'it' => 'Società anonima', 'en' => 'Corporation'],
            self::LimitedLiabilityCompany => ['de' => 'Gesellschaft mit beschränkter Haftung', 'fr' => 'Société à responsabilité limitée', 'it' => 'Società a garanzia limitata', 'en' => 'Limited Liability Company'],
            self::Cooperative => ['de' => 'Genossenschaft', 'fr' => 'Société coopérative', 'it' => 'Società cooperativa', 'en' => 'Cooperative'],
            self::Association => ['de' => 'Verein', 'fr' => 'Association', 'it' => 'Associazione', 'en' => 'Association'],
            self::Foundation => ['de' => 'Stiftung', 'fr' => 'Fondation', 'it' => 'Fondazione', 'en' => 'Foundation'],
            self::ForeignBranch => ['de' => 'Ausländische Zweigniederlassung', 'fr' => 'Succursale étrangère', 'it' => 'Succursale estera', 'en' => 'Foreign branch'],
            self::SpecialLegalForm => ['de' => 'Besondere Rechtsform', 'fr' => 'Nature juridique particulière', 'it' => 'Natura giuridica particolare', 'en' => 'Special legal form'],
            self::CollectiveInvestmentLimitedPartnership => ['de' => 'Kommanditgesellschaft für kollektive Kapitalanlagen', 'fr' => 'Société en commandite de placements collectifs', 'it' => 'Società in accomandita per investimenti collettivi di capitale', 'en' => 'Limited Partnership for collective investment schemes'],
            self::Sicav => ['de' => 'Investmentgesellschaft mit variablem Kapital (SICAV)', 'fr' => 'Société d’investissement à capital variable', 'it' => 'Società di investimento a capitale variabile', 'en' => 'Limited Partnership for collective investment schemes with a variable capital'],
            self::Sicaf => ['de' => 'Investmentgesellschaft mit festem Kapital (SICAF)', 'fr' => 'Société d’investissement à capital fixe', 'it' => 'Società di investimento a capitale fisso', 'en' => 'Limited Partnership for collective investment schemes with a fixed capital'],
            self::PublicSectorInstitution => ['de' => 'Institut des öffentlichen Rechts', 'fr' => 'Institut de droit public', 'it' => 'Istituto di diritto pubblico', 'en' => 'Public sector institution'],
            self::NonCommercialPowerOfAttorney => ['de' => 'Nichtkaufmännische Prokura', 'fr' => 'Procuration non commerciale', 'it' => 'Procura non commerciale', 'en' => 'Non commercial power of attorney'],
            self::OwnershipInUndividedShares => ['de' => 'Gemeinderschaft', 'fr' => 'Indivision', 'it' => 'Indivisione', 'en' => 'Ownership in undivided shares'],
            self::Branch => ['de' => 'Zweigniederlassung', 'fr' => 'Succursale', 'it' => 'Succursale', 'en' => 'Branch'],
        };
    }

    /** @return array{de: string, fr: string, it: string, en: string} */
    public function shortLabels(): array
    {
        return match ($this) {
            self::SoleProprietorship => ['de' => 'EIU', 'fr' => 'EI', 'it' => 'IPI', 'en' => 'SP'],
            self::GeneralPartnership => ['de' => 'KLG', 'fr' => 'SNC', 'it' => 'SNC', 'en' => 'GP'],
            self::LimitedPartnership => ['de' => 'KMG', 'fr' => 'SCm', 'it' => 'SAc', 'en' => 'LP'],
            self::PartnershipLimitedByShares => ['de' => 'KMAG', 'fr' => 'SCmA', 'it' => 'SAcA', 'en' => 'CUP'],
            self::Corporation => ['de' => 'AG', 'fr' => 'SA', 'it' => 'SA', 'en' => 'Ltd'],
            self::LimitedLiabilityCompany => ['de' => 'GmbH', 'fr' => 'Sàrl', 'it' => 'Sagl', 'en' => 'LLC'],
            self::Cooperative => ['de' => 'GEN', 'fr' => 'Scoop', 'it' => 'SCoop', 'en' => 'Coop'],
            self::Association => ['de' => 'Verein', 'fr' => 'Asso', 'it' => 'Asso', 'en' => 'Assn'],
            self::Foundation => ['de' => 'STIFT', 'fr' => 'Fond', 'it' => 'Fond', 'en' => 'Fdn'],
            self::ForeignBranch => ['de' => 'AZNL', 'fr' => 'SUCE', 'it' => 'SUCE', 'en' => 'FB'],
            self::SpecialLegalForm => ['de' => 'BES', 'fr' => 'PART', 'it' => 'PART', 'en' => 'SLF'],
            self::CollectiveInvestmentLimitedPartnership => ['de' => 'KmGK', 'fr' => 'SCmPC', 'it' => 'SAcIC', 'en' => 'LPCI'],
            self::Sicav => ['de' => 'SICAV', 'fr' => 'SICAV', 'it' => 'SICAV', 'en' => 'SICAV'],
            self::Sicaf => ['de' => 'SICAF', 'fr' => 'SICAF', 'it' => 'SICAF', 'en' => 'SICAF'],
            self::PublicSectorInstitution => ['de' => 'IÖR', 'fr' => 'IDP', 'it' => 'IDP', 'en' => 'PSI'],
            self::NonCommercialPowerOfAttorney => ['de' => 'NKP', 'fr' => 'PNC', 'it' => 'PNC', 'en' => 'NCPA'],
            self::OwnershipInUndividedShares => ['de' => 'GMDR', 'fr' => 'IND', 'it' => 'IND', 'en' => 'OUS'],
            self::Branch => ['de' => 'ZNL', 'fr' => 'Succ', 'it' => 'Succ', 'en' => 'BR'],
        };
    }

    /** Internal numeric id used by the Zefix REST API. */
    public function zefixId(): int
    {
        return match ($this) {
            self::SoleProprietorship => 1,
            self::GeneralPartnership => 2,
            self::Corporation => 3,
            self::LimitedLiabilityCompany => 4,
            self::Cooperative => 5,
            self::Association => 6,
            self::Foundation => 7,
            self::PublicSectorInstitution => 8,
            self::Branch => 9,
            self::LimitedPartnership => 10,
            self::ForeignBranch => 11,
            self::PartnershipLimitedByShares => 12,
            self::SpecialLegalForm => 13,
            self::OwnershipInUndividedShares => 14,
            self::Sicaf => 15,
            self::Sicav => 16,
            self::CollectiveInvestmentLimitedPartnership => 17,
            self::NonCommercialPowerOfAttorney => 18,
        };
    }

    public static function fromZefixId(?int $id): ?self
    {
        if ($id === null) {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->zefixId() === $id) {
                return $case;
            }
        }

        return null;
    }
}
