<?php
namespace App\Http\Controllers;

use App\Models\CustomerAttentionFlag;
use App\Models\CustomerCommercialSignal;
use App\Models\CustomerSuccessProfile;
use App\Models\CustomerSuccessTask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerSuccessDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q=trim((string)$request->query('q'));
        $attention=$request->query('attention');
        $profiles=CustomerSuccessProfile::query()->with([
                'customer.attentionFlags'=>fn($flags)=>$flags->where('status','open')->with('type')->latest('opened_at'),
                'lifecycleStatus',
            ])
            ->when($q,fn($builder)=>$builder->whereHas('customer',fn($c)=>$c->where('name','like',"%{$q}%")->orWhere('code','like',"%{$q}%")))
            ->when($attention,fn($builder)=>$builder->where('attention_level',$attention))
            ->when(!$attention,fn($builder)=>$builder->where(function($w){$w->whereIn('attention_level',['critical','high'])->orWhere('next_review_at','<=',now());}))
            ->orderByRaw("CASE attention_level WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'normal' THEN 4 ELSE 5 END")
            ->orderBy('next_review_at')->paginate(20)->withQueryString();

        $stats=[
            'customers'=>CustomerSuccessProfile::count(),
            'critical'=>CustomerSuccessProfile::where('attention_level','critical')->count(),
            'at_risk'=>CustomerSuccessProfile::whereHas('lifecycleStatus',fn($q)=>$q->where('code','AT_RISK'))->count(),
            'overdue_tasks'=>CustomerSuccessTask::open()->whereNotNull('due_at')->where('due_at','<',now())->count(),
            'open_flags'=>CustomerAttentionFlag::where('status','open')->count(),
            'active_signals'=>CustomerCommercialSignal::where('status','active')->count(),
        ];
        $tasks=CustomerSuccessTask::with(['customer','assignee','playbookRun.playbook'])->open()->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->limit(30)->get();
        $signals=CustomerCommercialSignal::with(['customer','owner'])->where('status','active')->latest()->limit(10)->get();
        return view('customer-success.dashboard',compact('profiles','stats','tasks','signals','q','attention'));
    }
}
