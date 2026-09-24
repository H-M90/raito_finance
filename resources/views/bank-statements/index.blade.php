@extends('layouts.app')
@section('title','مطابقة كشف البنك')
@section('page-title','مطابقة كشف حساب البنك')
@section('page-subtitle','ارفع Excel، صنّف الحركات، ثم اعتمدها لإنشاء سندات المصروف والتحصيل')
@section('content')
<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><div><h2>رفع كشف بنك جديد</h2><p>لن يتم إنشاء أي سند مالي قبل الاعتماد النهائي.</p></div></div>
        @if(auth()->user()->hasPermission('bank-statements.create'))
        <form method="post" action="{{ route('bank-statements.upload') }}" enctype="multipart/form-data" class="form-grid">@csrf
            <div class="form-group col-12"><label class="required">ملف Excel</label><input class="input" type="file" name="file" accept=".xlsx,.xls" required><div class="help">الحد الأقصى 20 MB. يدعم النظام اكتشاف أعمدة التاريخ والبيان والمدين والدائن تلقائيًا ثم يتيح مراجعتها.</div></div>
            <div class="form-group col-4"><label>اسم البنك</label><input class="input" name="bank_name" value="{{ old('bank_name') }}" placeholder="مثال: الراجحي"></div>
            <div class="form-group col-4"><label>الحساب / وصف الحساب</label><input class="input" name="account_name" value="{{ old('account_name') }}" placeholder="مثال: الحساب التشغيلي"></div>
            <div class="form-group col-4"><label class="required">العملة</label><select class="select" name="currency" required>@foreach($currencies as $code=>$label)<option value="{{ $code }}" @selected(old('currency','SAR')===$code)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-12"><button class="btn btn-primary">رفع وقراءة الملف</button></div>
        </form>
        @else<div class="alert alert-info">لديك صلاحية عرض كشوف البنك فقط.</div>@endif
    </div>
    <div class="card">
        <div class="card-header"><h2>طريقة العمل</h2></div>
        <div class="timeline">
            <div class="timeline-item"><h4>1. رفع الملف</h4><p>يحفظ الملف بصورة خاصة ويقرأ أول ورقة عمل.</p></div>
            <div class="timeline-item"><h4>2. ربط الأعمدة</h4><p>راجع أعمدة التاريخ والبيان والمرجع والمبالغ الداخلة والخارجة.</p></div>
            <div class="timeline-item"><h4>3. توزيع الحركات</h4><p>كل سطر يصبح مصروفًا أو تحصيلًا أو يتم تجاهله، مع استكمال بياناته.</p></div>
            <div class="timeline-item"><h4>4. الاعتماد</h4><p>الاعتماد ينشئ السندات الفعلية مرة واحدة فقط وبعملية كاملة دون ترحيل جزئي.</p></div>
        </div>
    </div>
</div>
<div class="card section-spaced">
    <div class="card-header"><div><h2>كشوف البنك المرفوعة</h2><p>{{ $batches->total() }} ملف</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>الملف</th><th>البنك</th><th>العملة</th><th>الحركات</th><th>الخارج</th><th>الداخل</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead><tbody>
    @forelse($batches as $batch)<tr>
        <td><strong>{{ $batch->original_filename }}</strong><br><small class="muted">{{ $batch->account_name ?: '—' }}</small></td>
        <td>{{ $batch->bank_name ?: '—' }}</td><td>{{ $batch->currency }}</td><td>{{ $batch->total_rows }}</td>
        <td class="money text-danger">{{ number_format((float)$batch->total_debit,2) }}</td><td class="money text-success">{{ number_format((float)$batch->total_credit,2) }}</td>
        <td><span class="badge {{ $batch->status==='approved'?'badge-success':($batch->status==='reviewing'?'badge-warning':'badge-info') }}">{{ ['mapping'=>'ربط الأعمدة','reviewing'=>'قيد المراجعة','approved'=>'معتمد'][$batch->status]??$batch->status }}</span></td>
        <td>{{ $batch->created_at->format('Y-m-d H:i') }}</td>
        <td><a class="btn btn-sm btn-light" href="{{ $batch->status==='mapping'?route('bank-statements.mapping',$batch):route('bank-statements.show',$batch) }}">فتح</a></td>
    </tr>@empty<tr><td colspan="9" class="empty-state">لم يتم رفع كشف بنك حتى الآن.</td></tr>@endforelse
    </tbody></table></div>{{ $batches->links() }}
</div>
@endsection
