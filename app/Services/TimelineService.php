<?php
namespace App\Services;
use App\Models\TimelineEvent;
use Illuminate\Database\Eloquent\Model;
class TimelineService
{
    public function record(int $customerId, string $type, string $title, ?string $description=null, ?Model $source=null, ?int $contractId=null, ?string $url=null, mixed $date=null): TimelineEvent
    {
        return TimelineEvent::create(['customer_id'=>$customerId,'contract_id'=>$contractId,'event_at'=>$date?:now(),'event_type'=>$type,'title'=>$title,'description'=>$description,'source_type'=>$source?->getMorphClass(),'source_id'=>$source?->getKey(),'url'=>$url,'created_by'=>auth()->id()]);
    }
}
