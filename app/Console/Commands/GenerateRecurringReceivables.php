<?php

namespace App\Console\Commands;

use App\Services\RecurringReceivableGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRecurringReceivables extends Command
{
    protected $signature = 'finance:generate-receivables {--until=}';
    protected $description = 'Generate upcoming monthly, annual and maintenance receivables before their due date without duplicates.';

    public function handle(RecurringReceivableGenerator $generator): int
    {
        $explicitUntil = $this->option('until') ? Carbon::parse($this->option('until'))->startOfDay() : null;
        $until = $generator->horizon($explicitUntil);
        $created = $generator->generateDue($until);
        $this->info("Created {$created} receivable(s) through {$until->toDateString()}.");
        return self::SUCCESS;
    }
}
