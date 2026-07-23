<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Facades;

use Illuminate\Support\Facades\Facade;
use Kokonut\SwissCompanyRegistry\Values\UidFactory;

/**
 * @method static \Kokonut\SwissCompanyRegistry\Values\Uid parse(\Kokonut\SwissCompanyRegistry\Values\Uid|string|null $value)
 * @method static \Kokonut\SwissCompanyRegistry\Values\Uid|null tryParse(\Kokonut\SwissCompanyRegistry\Values\Uid|string|null $value)
 * @method static bool isValid(\Kokonut\SwissCompanyRegistry\Values\Uid|string|null $value)
 * @method static string|null format(\Kokonut\SwissCompanyRegistry\Values\Uid|string|null $value)
 * @method static string|null formatVat(\Kokonut\SwissCompanyRegistry\Values\Uid|string|null $value, \Kokonut\SwissCompanyRegistry\Enums\VatSuffix $suffix = \Kokonut\SwissCompanyRegistry\Enums\VatSuffix::TVA)
 *
 * @see UidFactory
 */
class SwissUid extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UidFactory::class;
    }
}
