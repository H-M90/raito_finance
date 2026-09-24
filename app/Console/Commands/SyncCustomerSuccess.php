<?php
namespace App\Console\Commands;
use App\Services\CustomerSuccessService;
use Illuminate\Console\Command;
class SyncCustomerSuccess extends Command
{
    protected $signature='customer-success:sync';
    protected $description='مزامنة إشارات نجاح العملاء الآلية مثل التأخر المالي والتجديد والمراجعات الدورية';
    public function handle(CustomerSuccessService $service): int
    {
        $stats=$service->syncAutomations();
        foreach($stats as $key=>$value)$this->line("{$key}: {$value}");
        return self::SUCCESS;
    }
}
