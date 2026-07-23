<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Enums;

use Kokonut\SwissCompanyRegistry\Support\DisplayLocale;

/**
 * The 26 Swiss cantons, backed by their official two-letter abbreviation
 * as used by both Zefix and the UID register.
 */
enum Canton: string
{
    case AG = 'AG';
    case AI = 'AI';
    case AR = 'AR';
    case BE = 'BE';
    case BL = 'BL';
    case BS = 'BS';
    case FR = 'FR';
    case GE = 'GE';
    case GL = 'GL';
    case GR = 'GR';
    case JU = 'JU';
    case LU = 'LU';
    case NE = 'NE';
    case NW = 'NW';
    case OW = 'OW';
    case SG = 'SG';
    case SH = 'SH';
    case SO = 'SO';
    case SZ = 'SZ';
    case TG = 'TG';
    case TI = 'TI';
    case UR = 'UR';
    case VD = 'VD';
    case VS = 'VS';
    case ZG = 'ZG';
    case ZH = 'ZH';

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

    /** @return array{de: string, fr: string, it: string, en: string} */
    public function labels(): array
    {
        return match ($this) {
            self::AG => ['de' => 'Aargau', 'fr' => 'Argovie', 'it' => 'Argovia', 'en' => 'Aargau'],
            self::AI => ['de' => 'Appenzell Innerrhoden', 'fr' => 'Appenzell Rhodes-Intérieures', 'it' => 'Appenzello Interno', 'en' => 'Appenzell Innerrhoden'],
            self::AR => ['de' => 'Appenzell Ausserrhoden', 'fr' => 'Appenzell Rhodes-Extérieures', 'it' => 'Appenzello Esterno', 'en' => 'Appenzell Ausserrhoden'],
            self::BE => ['de' => 'Bern', 'fr' => 'Berne', 'it' => 'Berna', 'en' => 'Bern'],
            self::BL => ['de' => 'Basel-Landschaft', 'fr' => 'Bâle-Campagne', 'it' => 'Basilea Campagna', 'en' => 'Basel-Landschaft'],
            self::BS => ['de' => 'Basel-Stadt', 'fr' => 'Bâle-Ville', 'it' => 'Basilea Città', 'en' => 'Basel-Stadt'],
            self::FR => ['de' => 'Freiburg', 'fr' => 'Fribourg', 'it' => 'Friburgo', 'en' => 'Fribourg'],
            self::GE => ['de' => 'Genf', 'fr' => 'Genève', 'it' => 'Ginevra', 'en' => 'Geneva'],
            self::GL => ['de' => 'Glarus', 'fr' => 'Glaris', 'it' => 'Glarona', 'en' => 'Glarus'],
            self::GR => ['de' => 'Graubünden', 'fr' => 'Grisons', 'it' => 'Grigioni', 'en' => 'Graubünden'],
            self::JU => ['de' => 'Jura', 'fr' => 'Jura', 'it' => 'Giura', 'en' => 'Jura'],
            self::LU => ['de' => 'Luzern', 'fr' => 'Lucerne', 'it' => 'Lucerna', 'en' => 'Lucerne'],
            self::NE => ['de' => 'Neuenburg', 'fr' => 'Neuchâtel', 'it' => 'Neuchâtel', 'en' => 'Neuchâtel'],
            self::NW => ['de' => 'Nidwalden', 'fr' => 'Nidwald', 'it' => 'Nidvaldo', 'en' => 'Nidwalden'],
            self::OW => ['de' => 'Obwalden', 'fr' => 'Obwald', 'it' => 'Obvaldo', 'en' => 'Obwalden'],
            self::SG => ['de' => 'St. Gallen', 'fr' => 'Saint-Gall', 'it' => 'San Gallo', 'en' => 'St. Gallen'],
            self::SH => ['de' => 'Schaffhausen', 'fr' => 'Schaffhouse', 'it' => 'Sciaffusa', 'en' => 'Schaffhausen'],
            self::SO => ['de' => 'Solothurn', 'fr' => 'Soleure', 'it' => 'Soletta', 'en' => 'Solothurn'],
            self::SZ => ['de' => 'Schwyz', 'fr' => 'Schwytz', 'it' => 'Svitto', 'en' => 'Schwyz'],
            self::TG => ['de' => 'Thurgau', 'fr' => 'Thurgovie', 'it' => 'Turgovia', 'en' => 'Thurgau'],
            self::TI => ['de' => 'Tessin', 'fr' => 'Tessin', 'it' => 'Ticino', 'en' => 'Ticino'],
            self::UR => ['de' => 'Uri', 'fr' => 'Uri', 'it' => 'Uri', 'en' => 'Uri'],
            self::VD => ['de' => 'Waadt', 'fr' => 'Vaud', 'it' => 'Vaud', 'en' => 'Vaud'],
            self::VS => ['de' => 'Wallis', 'fr' => 'Valais', 'it' => 'Vallese', 'en' => 'Valais'],
            self::ZG => ['de' => 'Zug', 'fr' => 'Zoug', 'it' => 'Zugo', 'en' => 'Zug'],
            self::ZH => ['de' => 'Zürich', 'fr' => 'Zurich', 'it' => 'Zurigo', 'en' => 'Zurich'],
        };
    }
}
