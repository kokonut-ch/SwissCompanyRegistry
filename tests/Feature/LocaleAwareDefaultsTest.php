<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Facades\SwissUid;

it('follows the application locale for enum labels and the default VAT suffix when no display locale is configured', function (): void {
    app()->setLocale('fr');

    expect(LegalForm::LimitedLiabilityCompany->label())->toBe('Société à responsabilité limitée')
        ->and(SwissUid::formatVat('che 109 322 551'))->toEndWith('TVA');

    app()->setLocale('de');

    expect(SwissUid::formatVat('che 109 322 551'))->toEndWith('MWST');
});
