<?php

declare(strict_types=1);

use Kokonut\SwissCompanyRegistry\Enums\Canton;
use Kokonut\SwissCompanyRegistry\Enums\LegalForm;
use Kokonut\SwissCompanyRegistry\Exceptions\InvalidSearchQueryException;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;

it('wraps the term with wildcards when fuzzy', function (): void {
    expect(SearchQuery::make('aubry')->fuzzy()->effectiveName())->toBe('*aubry*')
        ->and(SearchQuery::make('*aubry*')->fuzzy()->effectiveName())->toBe('*aubry*')
        ->and(SearchQuery::make('aubry')->effectiveName())->toBe('aubry');
});

it('rejects an empty term', function (): void {
    SearchQuery::make('  *  ');
})->throws(InvalidSearchQueryException::class);

it('accepts canton as enum or string', function (): void {
    expect(SearchQuery::make('aubry')->canton('ju')->canton)->toBe(Canton::JU)
        ->and(SearchQuery::make('aubry')->canton(Canton::VD)->canton)->toBe(Canton::VD);
});

it('rejects an unknown canton', function (): void {
    SearchQuery::make('aubry')->canton('XX');
})->throws(InvalidSearchQueryException::class);

it('accepts a legal form as enum or eCH code', function (): void {
    expect(SearchQuery::make('aubry')->legalForm('0107')->legalForm)->toBe(LegalForm::LimitedLiabilityCompany)
        ->and(SearchQuery::make('aubry')->legalForm(LegalForm::Corporation)->legalForm)->toBe(LegalForm::Corporation);
});

it('rejects combining a canton with a commune filter', function (): void {
    SearchQuery::make('aubry')->canton('JU')->legalSeatId(6711);
})->throws(InvalidSearchQueryException::class);

it('rejects combining a commune filter with a canton', function (): void {
    SearchQuery::make('aubry')->legalSeatId(6711)->canton('JU');
})->throws(InvalidSearchQueryException::class);

it('tracks active-only and limit', function (): void {
    $query = SearchQuery::make('aubry')->includeInactive()->limit(5);

    expect($query->activeOnly)->toBeFalse()
        ->and($query->limit)->toBe(5);
});

it('fingerprints queries by their full state', function (): void {
    $a = SearchQuery::make('aubry')->canton('JU');
    $b = SearchQuery::make('aubry')->canton('JU');
    $c = SearchQuery::make('aubry')->canton('VD');

    expect($a->fingerprint())->toBe($b->fingerprint())
        ->and($a->fingerprint())->not->toBe($c->fingerprint());
});
