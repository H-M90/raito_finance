<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerSuccessTask;
use App\Models\InternalTask;
use App\Models\SalesLead;
use App\Models\SalesLeadTask;
use App\Models\User;
use App\Services\CustomerSuccessService;
use App\Services\SalesLeadService;
use App\Support\OwnRecordVisibility;
use App\Support\TaskCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskCenterController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->string('view')->toString() ?: 'today';
        if (! in_array($view, ['today','overdue','upcoming','done','all'], true)) $view = 'today';

        $base = DB::query()->fromSub($this->unionQuery(), 'task_center');
        $this->applyFilters($base, $request, $view);

        $tasks = $base
            ->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->paginate(30)
            ->withQueryString();

        $metricBase = DB::query()->fromSub($this->unionQuery(), 'task_center');
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $openStatuses = ['open','in_progress'];
        $metrics = [
            'today' => (clone $metricBase)->whereIn('status',$openStatuses)->whereBetween('due_at',[$todayStart,$todayEnd])->count(),
            'overdue' => (clone $metricBase)->whereIn('status',$openStatuses)->where('due_at','<',$todayStart)->count(),
            'meetings' => (clone $metricBase)->whereIn('status',$openStatuses)->whereBetween('due_at',[$todayStart,$todayEnd])->where('normalized_type','meeting')->count(),
            'done_today' => (clone $metricBase)->where('status','completed')->whereBetween('completed_at',[$todayStart,$todayEnd])->count(),
        ];

        $owners = User::query()->where('is_active',true)->orderBy('name')->get(['id','name']);
        return view('tasks.index', [
            'tasks'=>$tasks,
            'metrics'=>$metrics,
            'owners'=>$owners,
            'view'=>$view,
            'teams'=>TaskCatalog::TEAMS,
            'types'=>TaskCatalog::TYPES,
            'priorities'=>['critical'=>'حرجة','high'=>'عالية','medium'=>'متوسطة','low'=>'منخفضة'],
        ]);
    }

    public function targets(Request $request): JsonResponse
    {
        $type = $request->string('type')->toString();
        $q = trim($request->string('q')->toString());

        if ($type === 'lead') {
            $query = SalesLead::query()->whereNull('deleted_at')->whereNotIn('stage',['won','lost','disqualified']);
            if (OwnRecordVisibility::restricts(auth()->user())) {
                $query->where(fn($x)=>$x->where('owner_id',auth()->id())->orWhere('created_by',auth()->id()));
            }
            if ($q !== '') {
                $like = "%{$q}%";
                $query->where(fn($x)=>$x->where('company_name','like',$like)->orWhere('contact_name','like',$like)->orWhere('phone','like',$like)->orWhere('code','like',$like));
            }
            $items = $query->orderBy('company_name')->limit(15)->get(['id','company_name','contact_name','phone','code'])->map(fn($lead)=>[
                'id'=>$lead->id,
                'name'=>$lead->company_name,
                'meta'=>collect([$lead->contact_name,$lead->phone,$lead->code])->filter()->implode(' · '),
            ]);
            return response()->json(['items'=>$items]);
        }

        if ($type === 'customer') {
            $query = Customer::query()->whereNull('deleted_at');
            if (OwnRecordVisibility::restricts(auth()->user())) {
                $query->where('sales_owner_id', auth()->id());
            }
            if ($q !== '') {
                $like = "%{$q}%";
                $query->where(fn($x)=>$x->where('name','like',$like)->orWhere('contact_name','like',$like)->orWhere('phone','like',$like)->orWhere('code','like',$like));
            }
            $items = $query->orderBy('name')->limit(15)->get(['id','name','contact_name','phone','code'])->map(fn($customer)=>[
                'id'=>$customer->id,
                'name'=>$customer->name,
                'meta'=>collect([$customer->contact_name,$customer->phone,$customer->code])->filter()->implode(' · '),
            ]);
            return response()->json(['items'=>$items]);
        }

        return response()->json(['items'=>[]]);
    }

    public function store(Request $request, SalesLeadService $salesService, CustomerSuccessService $successService): RedirectResponse
    {
        $data = $this->validatePayload($request, true);
        $assignedTo = $this->resolveAssignee($data['assigned_to'] ?? null);

        if ($data['target_type'] === 'lead') {
            $lead = $this->findVisibleLead((int) $data['target_id']);
            $task = $lead->tasks()->create([
                'title'=>$data['title'],'description'=>$data['description'] ?? null,'team'=>$data['team'],'type'=>$data['type'],
                'priority'=>$data['priority'],'due_at'=>$data['due_at'],'status'=>'open','is_follow_up'=>false,
                'assigned_to'=>$assignedTo ?: $lead->owner_id ?: auth()->id(),'created_by'=>auth()->id(),
            ]);
            $salesService->log($lead, 'مهمة جديدة', TaskCatalog::typeLabel($task->type).': '.$task->title, auth()->id());
        } elseif ($data['target_type'] === 'customer') {
            $customer = $this->findVisibleCustomer((int) $data['target_id']);
            $task = CustomerSuccessTask::create([
                'customer_id'=>$customer->id,'title'=>$data['title'],'description'=>$data['description'] ?? null,
                'team'=>$data['team'],'type'=>$data['type'],'status'=>'open','priority'=>$this->toCustomerPriority($data['priority']),
                'assigned_to'=>$assignedTo ?: $customer->sales_owner_id ?: auth()->id(),'due_at'=>$data['due_at'],'created_by'=>auth()->id(),
            ]);
            $successService->recordEvent($customer,'task','TASK_CREATED',$task,['title'=>$task->title,'type'=>$task->type],auth()->id(),false);
        } else {
            InternalTask::create([
                'title'=>$data['title'],'description'=>$data['description'] ?? null,'team'=>$data['team'],'type'=>$data['type'],
                'priority'=>$data['priority'],'due_at'=>$data['due_at'],'status'=>'open','assigned_to'=>$assignedTo ?: auth()->id(),
                'created_by'=>auth()->id(),'updated_by'=>auth()->id(),
            ]);
        }

        return back()->with('success','تمت إضافة المهمة إلى مركز العمل.');
    }

    public function update(Request $request, string $source, int $task, SalesLeadService $salesService): RedirectResponse
    {
        $data = $this->validatePayload($request, false);
        $assignedTo = $this->resolveAssignee($data['assigned_to'] ?? null);

        if ($source === 'sales') {
            $record = SalesLeadTask::with('lead')->findOrFail($task);
            $this->authorizeSalesTask($record);
            $payload = [
                'title'=>$data['title'],'description'=>$data['description'] ?? null,'priority'=>$data['priority'],
                'due_at'=>$data['due_at'],'assigned_to'=>$assignedTo ?: $record->assigned_to,'team'=>$record->is_follow_up ? 'sales' : $data['team'],
            ];
            if (! $record->is_follow_up) $payload['type'] = $data['type'];
            $record->update($payload);
            if ($record->is_follow_up) {
                $record->lead->update([
                    'next_follow_up_at'=>$record->due_at,'next_follow_up_title'=>$record->title,
                    'next_follow_up_priority'=>$record->priority,'updated_by'=>auth()->id(),
                ]);
            }
            $salesService->log($record->lead,'تعديل مهمة','تم تحديث المهمة: '.$record->title,auth()->id());
        } elseif ($source === 'customer') {
            $record = CustomerSuccessTask::with('customer')->findOrFail($task);
            $this->authorizeCustomerTask($record);
            $protected = (bool) $record->playbook_run_id || str_starts_with($record->title, CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX);
            $payload = [
                'priority'=>$this->toCustomerPriority($data['priority']),'due_at'=>$data['due_at'],'assigned_to'=>$assignedTo ?: $record->assigned_to,
            ];
            if (! $protected) {
                $payload += ['title'=>$data['title'],'description'=>$data['description'] ?? null,'team'=>$data['team'],'type'=>$data['type']];
            }
            $record->update($payload);
        } elseif ($source === 'internal') {
            $record = InternalTask::findOrFail($task);
            $this->authorizeInternalTask($record);
            $record->update([
                'title'=>$data['title'],'description'=>$data['description'] ?? null,'team'=>$data['team'],'type'=>$data['type'],
                'priority'=>$data['priority'],'due_at'=>$data['due_at'],'assigned_to'=>$assignedTo ?: $record->assigned_to,'updated_by'=>auth()->id(),
            ]);
        } else {
            abort(404);
        }

        return back()->with('success','تم حفظ تعديلات المهمة.');
    }

    public function complete(string $source, int $task, SalesLeadService $salesService, CustomerSuccessService $successService): RedirectResponse
    {
        if ($source === 'sales') {
            $record = SalesLeadTask::with('lead')->findOrFail($task);
            $this->authorizeSalesTask($record);
            $salesService->completeTask($record->lead,$record,auth()->id());
        } elseif ($source === 'customer') {
            $record = CustomerSuccessTask::findOrFail($task);
            $this->authorizeCustomerTask($record);
            $successService->completeTask($record,null,auth()->id());
        } elseif ($source === 'internal') {
            $record = InternalTask::findOrFail($task);
            $this->authorizeInternalTask($record);
            if ($record->status !== 'completed') $record->update(['status'=>'completed','completed_at'=>now(),'completed_by'=>auth()->id(),'updated_by'=>auth()->id()]);
        } else abort(404);

        return back()->with('success','تم تسجيل المهمة كمكتملة.');
    }

    public function reopen(string $source, int $task): RedirectResponse
    {
        if ($source === 'sales') {
            $record = SalesLeadTask::with('lead')->findOrFail($task);
            $this->authorizeSalesTask($record);
            if ($record->status === 'completed') {
                $record->update(['status'=>'open','completed_at'=>null,'completed_by'=>null]);
                if ($record->is_follow_up) {
                    $record->lead->update([
                        'next_follow_up_at'=>$record->due_at,'next_follow_up_title'=>$record->title,'next_follow_up_priority'=>$record->priority,
                        'next_follow_up_type'=>in_array($record->type,array_keys(SalesLead::FOLLOW_UP_TYPES),true)?$record->type:'call','updated_by'=>auth()->id(),
                    ]);
                }
            }
        } elseif ($source === 'customer') {
            $record = CustomerSuccessTask::findOrFail($task);
            $this->authorizeCustomerTask($record);
            $protected = (bool) $record->playbook_run_id || str_starts_with($record->title, CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX);
            if ($protected) return back()->withErrors(['task'=>'لا يمكن إعادة فتح مهمة مرتبطة بخطة متابعة أو متابعة تلقائية مكتملة.']);
            $record->update(['status'=>'open','completed_at'=>null,'completed_by'=>null,'completion_notes'=>null]);
        } elseif ($source === 'internal') {
            $record = InternalTask::findOrFail($task);
            $this->authorizeInternalTask($record);
            $record->update(['status'=>'open','completed_at'=>null,'completed_by'=>null,'updated_by'=>auth()->id()]);
        } else abort(404);

        return back()->with('success','تمت إعادة فتح المهمة.');
    }

    private function unionQuery()
    {
        $salesTeam = Schema::hasColumn('sales_lead_tasks','team') ? 'COALESCE(t.team,\'sales\')' : "'sales'";
        $salesDescription = Schema::hasColumn('sales_lead_tasks','description') ? 't.description' : 'NULL';
        $sales = DB::table('sales_lead_tasks as t')
            ->join('sales_leads as l','l.id','=','t.sales_lead_id')
            ->leftJoin('users as u','u.id','=','t.assigned_to')
            ->whereNull('l.deleted_at')
            ->selectRaw("'sales' source, t.id task_id, 'lead' target_type, l.id target_id, l.company_name target_name, l.contact_name target_contact, {$salesTeam} team, t.type raw_type, CASE WHEN t.type IN ('call','message','whatsapp','email') THEN 'follow_up' ELSE t.type END normalized_type, t.title, {$salesDescription} description, t.priority, t.due_at, t.status, t.assigned_to, u.name assignee_name, t.completed_at, t.created_at, t.is_follow_up protected_task");
        if (OwnRecordVisibility::restricts(auth()->user())) {
            $sales->where(fn($q)=>$q->where('t.assigned_to',auth()->id())->orWhere('l.owner_id',auth()->id())->orWhere('l.created_by',auth()->id()));
        }

        $customerTeam = Schema::hasColumn('customer_success_tasks','team') ? "COALESCE(t.team,'account_management')" : "'account_management'";
        $customerType = Schema::hasColumn('customer_success_tasks','type') ? "COALESCE(t.type,'customer_success')" : "'customer_success'";
        $customerCreated = Schema::hasColumn('customer_success_tasks','created_by') ? 't.created_by' : 'NULL';
        $customer = DB::table('customer_success_tasks as t')
            ->join('customers as c','c.id','=','t.customer_id')
            ->leftJoin('users as u','u.id','=','t.assigned_to')
            ->whereNull('c.deleted_at')
            ->selectRaw("'customer' source, t.id task_id, 'customer' target_type, c.id target_id, c.name target_name, c.contact_name target_contact, {$customerTeam} team, {$customerType} raw_type, {$customerType} normalized_type, t.title, t.description, t.priority, t.due_at, t.status, t.assigned_to, u.name assignee_name, t.completed_at, t.created_at, CASE WHEN t.playbook_run_id IS NOT NULL OR t.title LIKE 'متابعة تلقائية — %' THEN 1 ELSE 0 END protected_task");
        if (OwnRecordVisibility::restricts(auth()->user())) {
            $hasCreatedBy = Schema::hasColumn('customer_success_tasks','created_by');
            $customer->where(function($q) use ($hasCreatedBy) {
                $q->where('t.assigned_to',auth()->id())->orWhere('c.sales_owner_id',auth()->id());
                if ($hasCreatedBy) $q->orWhere('t.created_by',auth()->id());
            });
        }

        $union = $sales->unionAll($customer);

        if (Schema::hasTable('internal_tasks')) {
            $internal = DB::table('internal_tasks as t')->leftJoin('users as u','u.id','=','t.assigned_to')
                ->selectRaw("'internal' source, t.id task_id, 'internal' target_type, NULL target_id, 'مهمة داخلية' target_name, NULL target_contact, t.team, t.type raw_type, t.type normalized_type, t.title, t.description, t.priority, t.due_at, t.status, t.assigned_to, u.name assignee_name, t.completed_at, t.created_at, 0 protected_task");
            if (OwnRecordVisibility::restricts(auth()->user())) {
                $internal->where(fn($q)=>$q->where('t.assigned_to',auth()->id())->orWhere('t.created_by',auth()->id()));
            }
            $union->unionAll($internal);
        }
        return $union;
    }

    private function applyFilters($query, Request $request, string $view): void
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $open = ['open','in_progress'];
        if ($view === 'today') $query->whereIn('status',$open)->whereBetween('due_at',[$todayStart,$todayEnd]);
        elseif ($view === 'overdue') $query->whereIn('status',$open)->where('due_at','<',$todayStart);
        elseif ($view === 'upcoming') $query->whereIn('status',$open)->where('due_at','>',$todayEnd);
        elseif ($view === 'done') $query->where('status','completed');

        $query->when($request->filled('team'),fn($q)=>$q->where('team',$request->team));
        if ($request->filled('type')) {
            $type = $request->string('type')->toString();
            if ($type === 'follow_up') $query->whereIn('raw_type',['follow_up','call','message','whatsapp','email']);
            else $query->where('normalized_type',$type);
        }
        $query->when($request->filled('owner_id'),fn($q)=>$q->where('assigned_to',$request->integer('owner_id')));
        if ($request->filled('priority')) {
            $priority = $request->string('priority')->toString();
            if ($priority === 'low') $query->whereIn('priority',['low','normal']); else $query->where('priority',$priority);
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn($q)=>$q->where('title','like',$term)->orWhere('description','like',$term)->orWhere('target_name','like',$term)->orWhere('target_contact','like',$term)->orWhere('assignee_name','like',$term));
        }
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $rules = [
            'title'=>['required','string','max:200'],
            'description'=>['nullable','string','max:4000'],
            'team'=>['required',Rule::in(array_keys(TaskCatalog::TEAMS))],
            'type'=>['required',Rule::in(array_keys(TaskCatalog::TYPES))],
            'priority'=>['required',Rule::in(['critical','high','medium','low'])],
            'due_at'=>['required','date'],
            'assigned_to'=>['nullable','integer','exists:users,id'],
        ];
        if ($creating) {
            $rules += [
                'target_type'=>['required',Rule::in(['lead','customer','internal'])],
                'target_id'=>['nullable','integer'],
            ];
        }
        $data = $request->validate($rules);
        if ($creating && $data['target_type'] !== 'internal' && empty($data['target_id'])) {
            abort(422,'اختر العميل أو العميل المحتمل المرتبط بالمهمة.');
        }
        return $data;
    }

    private function resolveAssignee(?int $assignedTo): ?int
    {
        if (! $assignedTo) return null;
        if ((int) $assignedTo !== (int) auth()->id() && ! auth()->user()->hasPermission('tasks.assign')) abort(403);
        return $assignedTo;
    }

    private function toCustomerPriority(string $priority): string
    {
        return $priority === 'low' ? 'normal' : $priority;
    }

    private function findVisibleLead(int $id): SalesLead
    {
        $query = SalesLead::query();
        if (OwnRecordVisibility::restricts(auth()->user())) {
            $query->where(fn($q)=>$q->where('owner_id',auth()->id())->orWhere('created_by',auth()->id()));
        }
        return $query->findOrFail($id);
    }

    private function findVisibleCustomer(int $id): Customer
    {
        $query = Customer::query();
        if (OwnRecordVisibility::restricts(auth()->user())) $query->where('sales_owner_id',auth()->id());
        return $query->findOrFail($id);
    }

    private function authorizeSalesTask(SalesLeadTask $task): void
    {
        if (! OwnRecordVisibility::restricts(auth()->user())) return;
        abort_unless((int)$task->assigned_to===(int)auth()->id() || (int)$task->lead?->owner_id===(int)auth()->id() || (int)$task->lead?->created_by===(int)auth()->id(),403);
    }

    private function authorizeCustomerTask(CustomerSuccessTask $task): void
    {
        if (! OwnRecordVisibility::restricts(auth()->user())) return;
        $task->loadMissing('customer');
        $createdBy = Schema::hasColumn('customer_success_tasks','created_by') ? (int)$task->getAttribute('created_by') : 0;
        abort_unless((int)$task->assigned_to===(int)auth()->id() || (int)$task->customer?->sales_owner_id===(int)auth()->id() || $createdBy===(int)auth()->id(),403);
    }

    private function authorizeInternalTask(InternalTask $task): void
    {
        if (! OwnRecordVisibility::restricts(auth()->user())) return;
        abort_unless((int)$task->assigned_to===(int)auth()->id() || (int)$task->created_by===(int)auth()->id(),403);
    }
}
