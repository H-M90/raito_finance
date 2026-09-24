@extends('layouts.app')
@section('title','ربط أعمدة كشف البنك')
@section('page-title','تأكيد أعمدة كشف البنك')
@section('page-subtitle',$batch->original_filename)
@section('content')
<div class="reconcile-steps"><span class="done">1 رفع الملف</span><span class="active">2 ربط الأعمدة</span><span>3 مراجعة الحركات</span><span>4 الاعتماد</span></div>
<div class="grid grid-2 bank-mapping-layout">
    <div class="card">
        <div class="card-header"><div><h2>ربط الأعمدة</h2><p>حدد عمود المدين وعمود الدائن كما يظهران في كشف حساب البنك.</p></div></div>
        <form method="post" action="{{ route('bank-statements.parse',$batch) }}" class="form-grid">@csrf
            @php($fields=['date'=>'التاريخ','description'=>'البيان / الوصف','reference'=>'رقم المرجع (اختياري)'])
            @foreach($fields as $key=>$label)<div class="form-group col-4"><label class="{{ $key==='reference'?'':'required' }}">{{ $label }}</label><select class="select" name="mapping[{{ $key }}]" {{ $key==='reference'?'':'required' }}><option value="">— اختر —</option>@foreach(($batch->detected_headers??[]) as $index=>$header)@if(trim((string)$header)!=='')<option value="{{ $index }}" @selected((string)old("mapping.$key",$batch->mapping[$key]??'')===(string)$index)>{{ $header }}</option>@endif @endforeach</select></div>@endforeach
            <div class="col-12 mapping-method-card"><strong>المبالغ</strong><p>المدين = الحركات الخارجة من البنك، والدائن = الحركات الداخلة. يجب اختيار العمودين.</p></div>
            <div class="form-group col-6"><label class="required">المدين / الخارج</label><select class="select" name="mapping[debit]" required><option value="">— اختر عمود المدين —</option>@foreach(($batch->detected_headers??[]) as $index=>$header)@if(trim((string)$header)!=='')<option value="{{ $index }}" @selected((string)old('mapping.debit',$batch->mapping['debit']??'')===(string)$index)>{{ $header }}</option>@endif @endforeach</select></div>
            <div class="form-group col-6"><label class="required">الدائن / الداخل</label><select class="select" name="mapping[credit]" required><option value="">— اختر عمود الدائن —</option>@foreach(($batch->detected_headers??[]) as $index=>$header)@if(trim((string)$header)!=='')<option value="{{ $index }}" @selected((string)old('mapping.credit',$batch->mapping['credit']??'')===(string)$index)>{{ $header }}</option>@endif @endforeach</select></div>
            <div class="col-12 form-actions-inline"><button class="btn btn-primary">قراءة الحركات</button><a class="btn btn-light" href="{{ route('bank-statements.index') }}">رجوع</a></div>
        </form>
    </div>
    <div class="card bank-preview-card"><div class="card-header"><div><h2>معاينة الملف</h2><p>البنك: {{ $batch->bank_name ?: 'غير محدد' }} · العملة: {{ $batch->currency }} · صف العناوين: {{ $batch->header_row }}</p></div><a class="btn btn-sm btn-light" href="{{ route('bank-statements.original',$batch) }}">الملف الأصلي</a></div><div class="table-wrap"><table class="preview-table"><tbody>@foreach(($batch->preview_rows??[]) as $row)<tr>@foreach($row as $cell)<td>{{ is_scalar($cell)?$cell:'' }}</td>@endforeach</tr>@endforeach</tbody></table></div></div>
</div>
@endsection
