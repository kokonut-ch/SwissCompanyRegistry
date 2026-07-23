<?php

declare(strict_types=1);

arch()->preset()->php();

arch('the package uses strict types everywhere')
    ->expect('Kokonut\SwissCompanyRegistry')
    ->toUseStrictTypes();

arch('DTOs are immutable')
    ->expect('Kokonut\SwissCompanyRegistry\Dto')
    ->toBeFinal()
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('Kokonut\SwissCompanyRegistry\Contracts')
    ->toBeInterfaces();
