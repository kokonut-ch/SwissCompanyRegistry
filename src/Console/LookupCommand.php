<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Console;

use Illuminate\Console\Command;
use Kokonut\SwissCompanyRegistry\Enums\UidValidationResult;
use Kokonut\SwissCompanyRegistry\Enums\VatValidationResult;
use Kokonut\SwissCompanyRegistry\Exceptions\SwissCompanyRegistryException;
use Kokonut\SwissCompanyRegistry\SwissCompanyRegistry;
use Kokonut\SwissCompanyRegistry\Values\Uid;

class LookupCommand extends Command
{
    protected $signature = 'swiss-company:lookup
        {uid : The UID, e.g. CHE-123.456.788}';

    protected $description = 'Look up a Swiss company by UID and validate it against the UID and VAT registers';

    public function handle(SwissCompanyRegistry $registry): int
    {
        $uid = Uid::tryParse($this->argument('uid'));

        if ($uid === null) {
            $this->error('This does not look like a Swiss UID. Expected something like CHE-123.456.788.');

            return self::FAILURE;
        }

        if (! $uid->hasValidCheckDigit()) {
            $this->warn("The check digit of {$uid->format()} is wrong; querying the registries anyway.");
        }

        try {
            $company = $registry->find($uid);
        } catch (SwissCompanyRegistryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($company === null) {
            $this->info("No company found for {$uid->format()}.");
        } else {
            $this->line('<info>'.$company->name.'</info> ('.$uid->format().')');

            $rows = array_filter([
                'Legal form' => $company->legalForm?->label(),
                'Status' => $company->status?->value,
                'Seat' => $company->legalSeat,
                'Canton' => $company->canton?->value,
                'Address' => $company->address?->oneLine(),
                'Purpose' => $company->purpose,
                'CH-ID' => $company->chId,
                'Cantonal excerpt' => $company->cantonalExcerptUrl,
            ], fn (?string $value): bool => $value !== null);

            foreach ($rows as $label => $value) {
                $this->line(sprintf('  <comment>%-16s</comment> %s', $label, $value));
            }
        }

        $uidResult = $registry->validateUid($uid);
        $vatResult = $registry->validateVatId($uid);

        $this->line('  <comment>UID register</comment>   '.match ($uidResult) {
            UidValidationResult::Valid => '<info>valid</info>',
            UidValidationResult::Invalid => '<error>not registered</error>',
            UidValidationResult::Unknown => 'could not be verified',
        });

        $this->line('  <comment>VAT register</comment>   '.match ($vatResult) {
            VatValidationResult::Active => '<info>active</info>',
            VatValidationResult::Inactive => '<error>not active</error>',
            VatValidationResult::Unknown => 'could not be verified',
        });

        return self::SUCCESS;
    }
}
