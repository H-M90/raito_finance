<?php
namespace Tests\Unit;
use App\Services\ContractDateService;
use PHPUnit\Framework\TestCase;
class ContractDateServiceTest extends TestCase
{
    public function test_first_maintenance_is_after_free_year(): void
    {
        $date=(new ContractDateService)->nextMaintenanceDate('2026-09-15');
        $this->assertSame('2027-09-15',$date->format('Y-m-d'));
    }
    public function test_old_contract_starts_from_calculation_date_and_paid_until(): void
    {
        $date=(new ContractDateService)->nextMaintenanceDate('2021-05-15','2026-01-01','2026-05-14');
        $this->assertSame('2026-05-15',$date->format('Y-m-d'));
    }
}
