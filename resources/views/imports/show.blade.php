@extends('layouts.app')
@section('title','مراجعة الاستيراد')
@section('page-title','مراجعة ملف الاستيراد')
@section('page-subtitle',$batch->original_filename)
@section('content')
<div class="grid grid-4">
    <div class="stat-card"><div class="label">التقدم</div><div class="value" data-import-progress>{{ $batch->progress_percentage }}%</div></div>
    <div class="stat-card"><div class="label">الصفوف الصحيحة</div><div class="value text-success">{{ $batch->valid_rows }}</div></div>
    <div class="stat-card"><div class="label">الصفوف غير المحلولة</div><div class="value text-danger">{{ $batch->invalid_rows }}</div></div>
    <div class="stat-card"><div class="label">{{ $batch->type==='raito_accounts' ? 'الحسابات / المتابعات' : 'العقود / الملاحق' }}</div><div class="value">{{ $batch->type==='raito_accounts' ? (($batch->summary['accounts']??0).' / '.($batch->summary['collections']??0)) : (($batch->summary['contracts']??0).' / '.($batch->summary['addendums']??0)) }}</div></div>
</div>
@if($batch->failure_message)<div class="alert alert-error">{{ $batch->failure_message }}</div>@endif
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<div class="card" style="margin-top:18px">
    <div class="card-header">
        <div><h2>معاينة المطابقات</h2><p>الحالة الحالية: <strong data-import-status>{{ $batch->status }}</strong></p></div>
        <div class="inline-actions">
            @if($batch->type==='raito_accounts'&&auth()->user()->hasPermission('imports.create'))<a class="btn btn-outline" href="{{ route('imports.mapping',$batch) }}">مطابقة الأعمدة</a>@endif
            @if($batch->error_file_path)<a class="btn btn-outline" href="{{ route('imports.errors',$batch) }}">تنزيل ملف الأخطاء</a>@endif
            @if($batch->status==='reviewing'&&auth()->user()->hasPermission('imports.commit'))
                <form method="post" action="{{ route('imports.commit',$batch) }}">@csrf<button class="btn btn-primary" @disabled($batch->invalid_rows>0)>اعتماد الاستيراد</button></form>
            @endif
            @if($batch->status==='completed'&&$batch->type!=='raito_accounts'&&auth()->user()->hasPermission('imports.cancel'))
                <form method="post" action="{{ route('imports.rollback',$batch) }}" data-confirm="سيتم التراجع عن السجلات المستوردة الآمنة فقط. متابعة؟">@csrf<button class="btn btn-danger">التراجع عن الاستيراد</button></form>
            @elseif(!in_array($batch->status,['completed','rolled_back'])&&auth()->user()->hasPermission('imports.cancel'))
                <form method="post" action="{{ route('imports.destroy',$batch) }}" data-confirm="إلغاء الدفعة وحذف ملفها؟">@csrf @method('delete')<button class="btn btn-danger">إلغاء الدفعة</button></form>
            @endif
        </div>
    </div>
    @if($batch->type==='raito_accounts'&&!empty($batch->summary['detected_columns']))
        <div class="alert alert-info">الأعمدة المكتشفة: {{ collect($batch->summary['detected_columns'])->flatten()->filter()->unique()->join('، ') }}</div>
    @endif
    <div class="table-wrap"><table><thead><tr><th>الورقة</th><th>الصف</th><th>النوع</th><th>المعاينة</th><th>الحالة / التحذيرات</th><th></th></tr></thead><tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row->sheet_name }}</td><td>{{ $row->row_number }}</td><td>{{ $row->row_type }}</td>
                <td>
                    @if($batch->type==='raito_accounts')
                        <strong>{{ $row->payload['customer_name'] ?? '' }}</strong><br>
                        <small>{{ $row->payload['_preview']['customer'] ?? '' }} · {{ $row->payload['_preview']['contract'] ?? '' }}</small>
                        @if(!empty($row->payload['products']))<br><small>{{ collect($row->payload['products'])->map(fn($p)=>$p['code'].': '.$p['quantity'])->join('، ') }}</small>@endif
                    @else
                        {{ collect($row->payload)->filter(fn($v)=>$v!==null&&$v!=='')->take(5)->map(fn($v,$k)=>$k.': '.$v)->join(' · ') }}
                    @endif
                </td>
                <td>
                    @if($row->status==='invalid')<span class="badge badge-danger">غير محلول</span><div class="text-danger">{{ collect($row->errors)->join('، ') }}</div>
                    @elseif($row->status==='imported')<span class="badge badge-success">تم الاستيراد</span>
                    @elseif($row->status==='skipped')<span class="badge badge-info">تم التخطي</span>
                    @elseif($row->status==='rolled_back')<span class="badge badge-info">تم التراجع</span>
                    @else<span class="badge badge-success">جاهز</span>@endif
                    @foreach($row->payload['_preview']['warnings']??[] as $warning)<div class="text-danger"><small>{{ $warning }}</small></div>@endforeach
                </td>
                <td>
                    @if($batch->type==='raito_accounts'&&$batch->status==='reviewing'&&auth()->user()->hasPermission('imports.create'))
                        <details class="row-editor"><summary class="btn btn-sm btn-light">حل المطابقة</summary>
                            <form method="post" action="{{ route('imports.rows.update',[$batch,$row]) }}" class="form-grid import-row-form">@csrf @method('put')
                                <div class="form-group col-6"><label>العميل الموجود</label><select class="input" name="payload[customer_id]"><option value="">اختر العميل</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((int)($row->payload['customer_id']??0)===$customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>@endforeach</select></div>
                                <div class="form-group col-6"><label>عند غياب العميل</label><select class="input" name="payload[customer_action]"><option value="">لا تنشئ تلقائياً</option><option value="create" @selected(($row->payload['customer_action']??null)==='create')>إنشاء العميل بعد الاعتماد</option></select></div>
                                @if($row->row_type==='account')<div class="form-group col-6"><label>العقد الموجود</label><select class="input" name="payload[contract_id]"><option value="">اختر العقد</option>@foreach($contracts as $contract)<option value="{{ $contract->id }}" @selected((int)($row->payload['contract_id']??0)===$contract->id)>{{ $contract->number }} — {{ $contract->customer->name }}</option>@endforeach</select></div><div class="form-group col-6"><label>عند غياب العقد</label><select class="input" name="payload[contract_action]"><option value="">لا تنشئ تلقائياً</option><option value="create" @selected(($row->payload['contract_action']??null)==='create')>إنشاء عقد مستورد بعد الاعتماد</option></select></div>@endif
                                <div class="form-group col-6"><label><input type="checkbox" name="payload[skip]" value="1" @checked(!empty($row->payload['skip']))> تخطي هذا الصف</label></div>
                                <div class="form-group col-12"><button class="btn btn-primary">حفظ وإعادة الفحص</button></div>
                            </form>
                        </details>
                    @elseif($batch->status==='reviewing'&&auth()->user()->hasPermission('imports.create'))
                        <details class="row-editor"><summary class="btn btn-sm btn-light">تعديل الصف</summary><form method="post" action="{{ route('imports.rows.update',[$batch,$row]) }}" class="form-grid import-row-form">@csrf @method('put') @foreach((\App\Services\ContractImportService::SHEETS[$row->sheet_name]??array_keys($row->payload)) as $field)<div class="form-group col-4"><label>{{ $field }}</label><input class="input" name="payload[{{ $field }}]" value="{{ $row->payload[$field]??'' }}"></div>@endforeach <div class="form-group col-12"><button class="btn btn-primary">حفظ وإعادة الفحص</button></div></form></details>
                    @endif
                </td>
            </tr>
        @empty<tr><td colspan="6" class="empty-state">يتم تجهيز الصفوف...</td></tr>@endforelse
    </tbody></table></div>
    {{ $rows->links() }}
</div>
@endsection
@if(in_array($batch->status,['queued','processing','commit_queued','committing']))<div hidden data-import-monitor data-status-url="{{ route('imports.status',$batch) }}"></div>@endif
