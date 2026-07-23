<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Console;

use Illuminate\Console\Command;
use Kokonut\SwissCompanyRegistry\Dto\CompanySummary;
use Kokonut\SwissCompanyRegistry\Exceptions\SwissCompanyRegistryException;
use Kokonut\SwissCompanyRegistry\Search\SearchQuery;
use Kokonut\SwissCompanyRegistry\SwissCompanyRegistry;

class SearchCommand extends Command
{
    protected $signature = 'swiss-company:search
        {name : Company name or term to search for}
        {--suggest : Match anywhere in the name instead of only from its beginning}
        {--canton= : Two-letter canton filter, e.g. JU}
        {--legal-form= : eCH-0097 legal form code, e.g. 0106}
        {--include-inactive : Include cancelled companies}
        {--limit=20 : Maximum number of rows}';

    protected $description = 'Search Swiss companies through the configured registry providers';

    public function handle(SwissCompanyRegistry $registry): int
    {
        $name = $this->argument('name');
        $canton = $this->option('canton');
        $legalForm = $this->option('legal-form');
        $limit = $this->option('limit');

        try {
            $query = SearchQuery::make(is_string($name) ? $name : '')
                ->fuzzy((bool) $this->option('suggest'))
                ->canton(is_string($canton) && $canton !== '' ? $canton : null)
                ->legalForm(is_string($legalForm) && $legalForm !== '' ? $legalForm : null)
                ->includeInactive((bool) $this->option('include-inactive'))
                ->limit(is_numeric($limit) ? (int) $limit : 20);

            $results = $registry->search($query);
        } catch (SwissCompanyRegistryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($results->isEmpty()) {
            $this->info('No company found.');

            return self::SUCCESS;
        }

        $this->table(
            ['UID', 'Name', 'Seat', 'Canton', 'Legal form', 'Status'],
            array_map(fn (CompanySummary $company): array => [
                $company->uid->format(),
                $company->name,
                $company->legalSeat ?? '',
                $company->canton->value ?? '',
                $company->legalForm?->shortLabel() ?? '',
                $company->status->value ?? '',
            ], $results->all()),
        );

        $this->line(sprintf('%d result(s) from the "%s" provider.', $results->count(), $results->provider));

        return self::SUCCESS;
    }
}
