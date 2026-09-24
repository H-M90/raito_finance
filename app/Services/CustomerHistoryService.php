<?php
namespace App\Services;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\OwnRecordVisibility;
class CustomerHistoryService
{
    public function generate(Customer $customer,?string $from=null,?string $to=null,?string $type=null): LengthAwarePaginator
    {
        return OwnRecordVisibility::apply($customer->timelineEvents())->when($from,fn($q)=>$q->whereDate('event_at','>=',$from))->when($to,fn($q)=>$q->whereDate('event_at','<=',$to))->when($type,fn($q)=>$q->where('event_type',$type))->orderByDesc('event_at')->orderByDesc('id')->paginate(30)->withQueryString();
    }
}
