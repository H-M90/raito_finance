<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class ContractDateService
{
    public function nextMaintenanceDate(CarbonInterface|string $serviceStart, CarbonInterface|string|null $calculationStart = null, CarbonInterface|string|null $maintenancePaidUntil = null): Carbon
    {
        $serviceStart = Carbon::parse($serviceStart)->startOfDay();
        $calculationStart = $calculationStart ? Carbon::parse($calculationStart)->startOfDay() : $serviceStart;
        $candidate = $serviceStart->copy()->addYear();

        while ($candidate->lt($calculationStart)) {
            $candidate->addYear();
        }

        if ($maintenancePaidUntil) {
            $paidCandidate = Carbon::parse($maintenancePaidUntil)->startOfDay()->addDay();
            if ($paidCandidate->gt($candidate)) {
                $candidate = $paidCandidate;
            }
        }

        return $candidate;
    }

    public function nextBillingDate(string $cycle, CarbonInterface|string $serviceStart, CarbonInterface|string|null $calculationStart = null): ?Carbon
    {
        if ($cycle === 'one_time') return null;

        $date = Carbon::parse($serviceStart)->startOfDay();
        $floor = $calculationStart ? Carbon::parse($calculationStart)->startOfDay() : $date;
        $step = $cycle === 'monthly' ? 'addMonthNoOverflow' : 'addYear';

        while ($date->lt($floor)) {
            $date->{$step}();
        }

        return $date;
    }
}
