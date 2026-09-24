<?php

namespace App\Http\Controllers;

use App\Models\SalesLead;
use App\Models\SalesLeadInterest;
use App\Models\SalesLeadTask;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Services\SalesLeadService;
use App\Support\SalesLeadVisibility;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesLeadController extends Controller
{
    public function index(Request $request): View
    {
        $base = $this->visibleQuery();
        $stageCounts = (clone $base)->selectRaw('stage, COUNT(*) total')->groupBy('stage')->pluck('total','stage');
        $metrics = [
            'total'=>(clone $base)->count(),
            'contacted'=>(clone $base)->where('stage','contacted')->count(),
            'qualified'=>(clone $base)->where('qualification','qualified')->whereNotIn('stage',['won','lost','disqualified'])->count(),
            'advanced'=>(clone $base)->whereIn('stage',['demo','proposal','evaluation','negotiation','contract_pending'])->count(),
            'won'=>(clone $base)->where('stage','won')->count(),
            'lost'=>(clone $base)->whereIn('stage',['lost','disqualified'])->count(),
        ];

        $query = $this->visibleQuery()->with('owner')->withCount('activities');
        $query->search($request->string('q')->toString())
            ->when($request->filled('stage'), fn($q)=>$q->where('stage',$request->stage))
            ->when($request->filled('owner_id'), fn($q)=>$q->where('owner_id',$request->integer('owner_id')))
            ->when($request->filled('source'), fn($q)=>$q->where('source',$request->source));
        $this->applyQuickFilter($query, $request->string('quick')->toString());

        $sort = in_array($request->sort,['company_name','stage','rating','last_activity_at','next_follow_up_at','created_at'],true) ? $request->sort : 'created_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int)$request->per_page,[20,50,100],true) ? (int)$request->per_page : 20;
        $leads = $query->orderBy($sort,$direction)->paginate($perPage)->withQueryString();

        $owners = SalesLeadVisibility::restrictsToAssigned()
            ? User::whereKey(auth()->id())->get(['id','name'])
            : User::where('is_active',true)->orderBy('name')->get(['id','name']);
        $quickCounts = $this->quickCounts();
        return view('sales-leads.index', compact('leads','owners','metrics','quickCounts','stageCounts'));
    }

    public function tasks(Request $request): RedirectResponse
    {
        return redirect()->route('tasks.index', array_filter([
            'view'=>$request->string('view')->toString() ?: 'today',
            'team'=>'sales',
            'type'=>$request->string('type')->toString() ?: null,
            'owner_id'=>$request->integer('owner_id') ?: null,
            'q'=>$request->string('q')->toString() ?: null,
        ]));
    }

    public function create(): View
    {
        $owners=$this->assignableOwners();
        return view('sales-leads.form',['lead'=>new SalesLead,'owners'=>$owners]);
    }

    public function store(Request $request, NumberGenerator $numbers, SalesLeadService $service): RedirectResponse
    {
        $data = $this->validateLead($request, true);
        if (($data['stage'] ?? 'lead') === 'won') {
            return back()->withErrors(['stage'=>'مرحلة تم البيع لا تُنشأ يدويًا؛ استخدم التحويل إلى عميل بعد التأهيل.'])->withInput();
        }
        $data['code']=$numbers->uniqueCode('sales_leads','LEAD');
        $data['created_by']=auth()->id();
        $data['updated_by']=auth()->id();
        $data['owner_id']=$data['owner_id'] ?? auth()->id();
        if ((int) $data['owner_id'] !== (int) auth()->id() && ! auth()->user()->hasPermission('sales-leads.assign')) {
            $data['owner_id'] = auth()->id();
        }
        if (in_array($data['stage'] ?? 'lead', ['lost','disqualified'], true)) {
            $data['next_follow_up_at']=null;
            $data['next_follow_up_type']=null;
            $data['next_follow_up_priority']=null;
            $data['next_follow_up_title']=null;
        } else {
            if (empty($data['next_follow_up_at'])) $data['next_follow_up_at']=now()->addDay()->setTime(10,0);
            $data['next_follow_up_type']=$data['next_follow_up_type'] ?? 'call';
            $data['next_follow_up_priority']=$data['next_follow_up_priority'] ?? 'medium';
            $data['next_follow_up_title']=$data['next_follow_up_title'] ?? 'متابعة أولية';
        }

        $lead = DB::transaction(function() use($data,$request,$service){
            $lead=SalesLead::create($data);
            if ($request->filled('initial_note')) $lead->notes()->create(['body'=>$request->initial_note,'created_by'=>auth()->id()]);
            $service->log($lead,'إنشاء العميل المحتمل','تمت إضافة العميل المحتمل إلى رحلة المبيعات.');
            $service->syncFollowUp($lead);
            return $lead;
        });
        if (! SalesLeadVisibility::allows($lead)) {
            return redirect()->route('sales-leads.index')->with('success','تم إنشاء العميل المحتمل وإسناده للمسؤول المحدد.');
        }
        return redirect()->route('sales-leads.show',$lead)->with('success','تم إنشاء العميل المحتمل وإضافة المتابعة القادمة.');
    }

    public function show(SalesLead $salesLead): View
    {
        $this->authorizeLead($salesLead);
        $salesLead->load([
            'owner','customer','interests'=>fn($q)=>$q->orderBy('name'),
            'activities'=>fn($q)=>$q->with('creator')->latest('occurred_at')->limit(60),
            'notes'=>fn($q)=>$q->with('creator')->latest()->limit(30),
            'tasks'=>fn($q)=>$q->with(['assignee','completedBy'])->orderByRaw("CASE status WHEN 'open' THEN 1 ELSE 2 END")->orderBy('due_at')->limit(40),
        ]);
        $owners=$this->assignableOwners();
        return view('sales-leads.show',['lead'=>$salesLead,'owners'=>$owners]);
    }

    public function edit(SalesLead $salesLead): View
    {
        $this->authorizeLead($salesLead);
        $owners=$this->assignableOwners();
        return view('sales-leads.form',['lead'=>$salesLead,'owners'=>$owners]);
    }

    public function update(Request $request, SalesLead $salesLead, SalesLeadService $service): RedirectResponse
    {
        $this->authorizeLead($salesLead);
        $before=$salesLead->only(['stage','rating','responded','qualification','owner_id','next_follow_up_at','next_follow_up_type','next_follow_up_title']);
        $data=$this->validateLead($request, false);
        if (($data['stage'] ?? null) === 'won' && ! $salesLead->customer_id) {
            return back()->withErrors(['stage'=>'مرحلة تم البيع تُسجل تلقائيًا عند تحويل العميل المحتمل إلى عميل. استخدم زر «تحويل إلى عميل».'])->withInput();
        }
        if ($salesLead->customer_id && array_key_exists('stage',$data) && $data['stage'] !== 'won') {
            return back()->withErrors(['stage'=>'تم تحويل هذا السجل إلى عميل بالفعل، لذلك لا يمكن إرجاع مرحلة المبيعات إلى مرحلة سابقة.'])->withInput();
        }
        if (array_key_exists('owner_id',$data) && (int)$data['owner_id'] !== (int)$salesLead->owner_id && ! auth()->user()->hasPermission('sales-leads.assign')) abort(403);
        $data['updated_by']=auth()->id();
        $salesLead->update($data);
        $salesLead->refresh();
        if (in_array($salesLead->stage, ['lost','disqualified'], true)) {
            $salesLead->tasks()->where('status','open')->update(['status'=>'cancelled']);
            if ($salesLead->next_follow_up_at) {
                $salesLead->update([
                    'next_follow_up_at'=>null, 'next_follow_up_type'=>null, 'next_follow_up_priority'=>null, 'next_follow_up_title'=>null,
                    'updated_by'=>auth()->id(),
                ]);
                $salesLead->refresh();
            }
        }
        $labels=[
            'stage'=>fn($v)=>SalesLead::STAGES[$v]??$v,
            'rating'=>fn($v)=>SalesLead::RATINGS[$v]??$v,
            'responded'=>fn($v)=>$v?'نعم':'لا',
            'qualification'=>fn($v)=>SalesLead::QUALIFICATIONS[$v]??$v,
        ];
        foreach ($labels as $field=>$label) {
            if (array_key_exists($field,$data) && (string)$before[$field] !== (string)$salesLead->$field) {
                $service->log($salesLead,'تحديث حالة المبيعات',sprintf('تم تغيير %s من "%s" إلى "%s".', $this->fieldLabel($field), $label($before[$field]), $label($salesLead->$field)));
            }
        }
        if (array_key_exists('owner_id',$data) && (string)$before['owner_id'] !== (string)$salesLead->owner_id) {
            $oldOwner = $before['owner_id'] ? User::find($before['owner_id'])?->name : 'غير معين';
            $newOwner = $salesLead->owner_id ? User::find($salesLead->owner_id)?->name : 'غير معين';
            $service->log($salesLead,'تغيير مسؤول المبيعات','تم تغيير المسؤول من "'.($oldOwner ?: 'غير معين').'" إلى "'.($newOwner ?: 'غير معين').'".');
        }
        if (array_key_exists('next_follow_up_at',$data) || array_key_exists('next_follow_up_type',$data) || array_key_exists('next_follow_up_title',$data)) {
            $service->syncFollowUp($salesLead);
            $service->log($salesLead,'تحديث المتابعة القادمة','تم تحديث موعد أو نوع المتابعة القادمة.');
        }
        if (! SalesLeadVisibility::allows($salesLead)) {
            return redirect()->route('sales-leads.index')->with('success','تم تحديث العميل المحتمل وإسناده للمسؤول المحدد.');
        }
        return back()->with('success','تم تحديث رحلة العميل المحتمل.');
    }

    public function addNote(Request $request, SalesLead $salesLead, SalesLeadService $service): RedirectResponse
    {
        $this->authorizeLead($salesLead);
        $data=$request->validate(['body'=>'required|string|max:5000']);
        $salesLead->notes()->create(['body'=>$data['body'],'created_by'=>auth()->id()]);
        $service->log($salesLead,'ملاحظة',$data['body']);
        return back()->with('success','تمت إضافة الملاحظة.');
    }

    public function addInterest(Request $request, SalesLead $salesLead, SalesLeadService $service): RedirectResponse
    {
        $this->authorizeLead($salesLead);
        $data=$request->validate(['name'=>'required|string|max:160']);
        $interest=$salesLead->interests()->firstOrCreate(['name'=>trim($data['name'])],['created_by'=>auth()->id()]);
        if ($interest->wasRecentlyCreated) $service->log($salesLead,'إضافة اهتمام','تمت إضافة "'.$interest->name.'" إلى احتياجات العميل.');
        return back()->with('success','تم حفظ اهتمام العميل.');
    }

    public function removeInterest(SalesLead $salesLead, SalesLeadInterest $interest, SalesLeadService $service): RedirectResponse
    {
        $this->authorizeLead($salesLead);
        abort_unless((int)$interest->sales_lead_id===(int)$salesLead->id,404);
        $name=$interest->name; $interest->delete();
        $service->log($salesLead,'حذف اهتمام','تم حذف "'.$name.'" من احتياجات العميل.');
        return back()->with('success','تم حذف الاهتمام.');
    }

    public function addTask(Request $request, SalesLead $salesLead, SalesLeadService $service): RedirectResponse
    {
        $this->authorizeLead($salesLead);
        $data=$request->validate([
            'title'=>'required|string|max:200','type'=>['required',Rule::in(array_keys(SalesLead::TASK_TYPES))],
            'priority'=>['required',Rule::in(array_keys(SalesLead::PRIORITIES))],'due_at'=>'required|date','assigned_to'=>'nullable|exists:users,id',
        ]);
        $data['assigned_to'] = $data['assigned_to'] ?? $salesLead->owner_id ?? auth()->id();
        if ((int) $data['assigned_to'] !== (int) auth()->id() && ! auth()->user()->hasPermission('sales-leads.assign')) {
            $data['assigned_to'] = auth()->id();
        }
        $task=$salesLead->tasks()->create($data+['status'=>'open','is_follow_up'=>false,'created_by'=>auth()->id()]);
        $service->log($salesLead,'مهمة جديدة',(SalesLead::TASK_TYPES[$task->type]??'مهمة').': '.$task->title);
        return back()->with('success','تمت إضافة المهمة.');
    }

    public function completeTask(SalesLead $salesLead, SalesLeadTask $task, SalesLeadService $service): RedirectResponse
    {
        $this->authorizeLead($salesLead);
        abort_unless((int)$task->sales_lead_id===(int)$salesLead->id,404);
        $service->completeTask($salesLead,$task,auth()->id());
        return back()->with('success','تم تسجيل المهمة كمكتملة.');
    }

    public function convert(SalesLead $salesLead, SalesLeadService $service): RedirectResponse
    {
        $this->authorizeLead($salesLead);
        $customer=$service->convertToCustomer($salesLead,auth()->id());
        return redirect()->route('customers.show',$customer)->with('success','تم تحويل العميل المحتمل إلى عميل بنجاح.');
    }

    public function bulkUpdate(Request $request, SalesLeadService $service): RedirectResponse
    {
        $data=$request->validate([
            'lead_ids'=>'required|array|min:1','lead_ids.*'=>'integer|exists:sales_leads,id',
            'action'=>['required',Rule::in(['stage','owner'])],
            'stage'=>['nullable',Rule::in(array_keys(SalesLead::STAGES))],
            'owner_id'=>'nullable|exists:users,id',
        ]);
        if ($data['action']==='owner' && ! auth()->user()->hasPermission('sales-leads.assign')) abort(403);
        if ($data['action']==='stage' && ($data['stage'] ?? null) === 'won') {
            return back()->withErrors(['stage'=>'مرحلة تم البيع تُسجل فقط من إجراء «تحويل إلى عميل» بعد اكتمال التأهيل.']);
        }
        $leads=$this->visibleQuery()->whereIn('id',$data['lead_ids'])->get();
        if ($data['action']==='stage' && $leads->contains(fn($lead)=>(bool)$lead->customer_id)) {
            return back()->withErrors(['stage'=>'التحديد يحتوي على سجل تم تحويله إلى عميل بالفعل؛ لا يمكن تغيير مرحلته جماعيًا.']);
        }
        DB::transaction(function() use($leads,$data,$service){
            foreach($leads as $lead){
                if($data['action']==='stage' && !empty($data['stage'])){
                    $old=$lead->stage; $lead->update(['stage'=>$data['stage'],'updated_by'=>auth()->id()]);
                    if (in_array($lead->stage, ['lost','disqualified'], true)) {
                        $lead->tasks()->where('status','open')->update(['status'=>'cancelled']);
                        $lead->update(['next_follow_up_at'=>null,'next_follow_up_type'=>null,'next_follow_up_priority'=>null,'next_follow_up_title'=>null]);
                    }
                    if($old!==$lead->stage)$service->log($lead,'تغيير المرحلة','تم نقل العميل من '.(SalesLead::STAGES[$old]??$old).' إلى '.(SalesLead::STAGES[$lead->stage]??$lead->stage).'.');
                }
                if($data['action']==='owner' && !empty($data['owner_id'])) {
                    $oldOwnerId = $lead->owner_id;
                    $lead->update(['owner_id'=>$data['owner_id'],'updated_by'=>auth()->id()]);
                    if ((int)$oldOwnerId !== (int)$lead->owner_id) {
                        $service->log($lead,'تغيير مسؤول المبيعات','تم إعادة تعيين مسؤول العميل المحتمل.');
                    }
                }
            }
        });
        return back()->with('success','تم تحديث '.number_format($leads->count()).' عميل محتمل.');
    }

    private function visibleQuery()
    {
        return SalesLeadVisibility::apply(SalesLead::query());
    }

    private function authorizeLead(SalesLead $lead): void
    {
        abort_unless(SalesLeadVisibility::allows($lead), 403);
    }

    private function assignableOwners()
    {
        if (! auth()->user()->hasPermission('sales-leads.assign')) {
            return User::whereKey(auth()->id())->get(['id','name']);
        }

        return User::where('is_active',true)->orderBy('name')->get(['id','name']);
    }

    private function validateLead(Request $request, bool $creating): array
    {
        $rules=[
            'company_name'=>[$creating?'required':'sometimes','string','max:255'],
            'contact_name'=>'nullable|string|max:255','phone'=>'nullable|string|max:40','email'=>'nullable|email|max:255','city'=>'nullable|string|max:100','sector'=>'nullable|string|max:120',
            'source'=>['nullable',Rule::in(array_keys(SalesLead::SOURCES))],'owner_id'=>'nullable|exists:users,id',
            'stage'=>['nullable',Rule::in(array_keys(SalesLead::STAGES))],'rating'=>['nullable',Rule::in(array_keys(SalesLead::RATINGS))],
            'responded'=>'nullable|boolean','qualification'=>['nullable',Rule::in(array_keys(SalesLead::QUALIFICATIONS))],
            'priority'=>['nullable',Rule::in(array_keys(SalesLead::PRIORITIES))],'favorite'=>'nullable|boolean',
            'next_follow_up_at'=>'nullable|date','next_follow_up_type'=>['nullable',Rule::in(array_keys(SalesLead::FOLLOW_UP_TYPES))],
            'next_follow_up_priority'=>['nullable',Rule::in(array_keys(SalesLead::PRIORITIES))],'next_follow_up_title'=>'nullable|string|max:200',
            'lost_reason'=>'nullable|string|max:3000','initial_note'=>'nullable|string|max:5000',
        ];
        $data=$request->validate($rules);
        if (isset($data['responded'])) $data['responded']=(bool)$data['responded'];
        if (isset($data['favorite'])) $data['favorite']=(bool)$data['favorite'];
        if (isset($data['next_follow_up_at']) && $data['next_follow_up_at']) $data['next_follow_up_at']=Carbon::parse($data['next_follow_up_at']);
        unset($data['initial_note']);
        if ($creating) {
            $data['stage']=$data['stage']??'lead'; $data['rating']=$data['rating']??'medium';
            $data['qualification']=$data['qualification']??'evaluating'; $data['priority']=$data['priority']??'medium';
        }
        return $data;
    }

    private function applyQuickFilter($query, string $quick): void
    {
        $terminal=['won','lost','disqualified'];
        match($quick){
            'today'=>$query->whereNotIn('stage',$terminal)->whereDate('next_follow_up_at',today()),
            'tomorrow'=>$query->whereNotIn('stage',$terminal)->whereDate('next_follow_up_at',today()->addDay()),
            'week'=>$query->whereNotIn('stage',$terminal)->whereBetween('next_follow_up_at',[now()->startOfDay(),now()->endOfWeek()]),
            'overdue'=>$query->whereNotNull('next_follow_up_at')->where('next_follow_up_at','<',now())->whereNotIn('stage',$terminal),
            'nofollowup'=>$query->whereNull('next_follow_up_at')->whereNotIn('stage',$terminal),
            'unanswered'=>$query->where('responded',false)->whereNotIn('stage',$terminal),
            'hot'=>$query->where('rating','hot')->whereNotIn('stage',$terminal),
            'qualified'=>$query->where('qualification','qualified')->whereNotIn('stage',$terminal),
            'meetingToday'=>$query->whereNotIn('stage',$terminal)->where('next_follow_up_type','meeting')->whereDate('next_follow_up_at',today()),
            'noactivity'=>$query->whereNotIn('stage',$terminal)->where(function($q){$q->whereNull('last_activity_at')->orWhere('last_activity_at','<',now()->subDays(7));}),
            'won'=>$query->where('stage','won'),
            default=>null,
        };
    }

    private function quickCounts(): array
    {
        $keys=['today','overdue','unanswered','hot','qualified','noactivity']; $out=[];
        foreach($keys as $key){$q=$this->visibleQuery();$this->applyQuickFilter($q,$key);$out[$key]=$q->count();}
        return $out;
    }

    private function fieldLabel(string $field): string
    {
        return ['stage'=>'المرحلة','rating'=>'التقييم','responded'=>'حالة الرد','qualification'=>'التأهيل'][$field]??$field;
    }
}
