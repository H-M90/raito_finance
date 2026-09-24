@extends('layouts.app')
@php($editing=$collection->exists)
@section('title',$editing?'تعديل سند قبض':'سند قبض جديد')
@section('page-title',$editing?'تعديل سند القبض '.$collection->number:'تسجيل سند قبض')
@section('page-subtitle','اختر العميل ثم العقد، وبعدها حدد الدفعة أو الصيانة أو استحقاق الملحق الذي يتم سداده')
@section('content')
<form method="post" enctype="multipart/form-data"
      action="{{ $editing?route('collections.update',$collection):route('collections.store') }}"
      data-collection-form data-customer-dependent-form data-editing="{{ $editing?1:0 }}" data-outstanding-url="{{ route('collections.outstanding') }}">
    @csrf
    @if($editing) @method('put') @endif

    <div class="form-section">
        <div class="form-section-title">
            <div><h3>مصدر التحصيل</h3><div class="help">العقد هو مصدر العملة والاستحقاقات؛ لن تظهر استحقاقات من عقود أخرى.</div></div>
        </div>
        <div class="form-grid">
            <x-reference-picker label="العميل" name="customer_id" wrapper-class="col-4" :required="true"
                data-remote-url="{{ route('lookup.customers') }}" data-parent-customer
                :create-url="route('customers.create')" create-label="عميل جديد"
                :can-create="auth()->user()->hasPermission('customers.create')">
                <option value="">اختر العميل</option>
                @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id',$selectedCustomer)==$c->id)>{{ $c->name }}</option>@endforeach
            </x-reference-picker>

            <x-reference-picker label="العقد" name="contract_id" wrapper-class="col-5" :required="true"
                data-contract-select data-dependent-contract data-remote-url="{{ route('lookup.contracts') }}"
                :create-url="route('contracts.create')" create-label="عقد جديد"
                :can-create="auth()->user()->hasPermission('contracts.create')">
                <option value="">اختر العقد</option>
                @foreach($contracts as $contract)
                    <option value="{{ $contract->id }}" data-customer="{{ $contract->customer_id }}" data-currency="{{ $contract->currency }}" @selected(old('contract_id',$selectedContract)==$contract->id)>
                        {{ $contract->number }}
                    </option>
                @endforeach
            </x-reference-picker>

            <div class="form-group col-3">
                <label>عملة العقد</label>
                <div class="readonly-value" data-collection-currency-label>{{ old('currency',$selectedCurrency) ?: '—' }}</div>
                <input type="hidden" name="currency" value="{{ old('currency',$selectedCurrency) }}" data-collection-currency>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-title"><h3>بيانات سند القبض</h3><span class="badge badge-success">يتأكد فور الحفظ</span></div>
        <div class="form-grid">
            <div class="form-group col-3"><label>التاريخ</label><input class="input" type="date" name="collection_date" value="{{ old('collection_date',$collection->collection_date?->format('Y-m-d')??today()->format('Y-m-d')) }}" required></div>
            <div class="form-group col-3"><label>القيمة</label><input class="input" type="number" min=".01" step=".01" name="amount" value="{{ old('amount',$collection->amount) }}" required></div>
            <div class="form-group col-3"><label>طريقة التحصيل</label><select class="select" name="payment_method" required>@foreach($methods as $code=>$label)<option value="{{ $code }}" @selected(old('payment_method',$collection->payment_method)===$code)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-group col-3"><label>رقم المرجع البنكي/الشيك</label><input class="input" name="reference_no" value="{{ old('reference_no',$collection->reference_no) }}"></div>
            <div class="form-group col-3"><label>المحصل</label><input class="input" name="collector_name" value="{{ old('collector_name',$collection->collector_name) }}"></div>
            <div class="form-group col-3"><label>المرفق</label><input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
            <div class="form-group col-6"><label>ملاحظات</label><input class="input" name="notes" value="{{ old('notes',$collection->notes) }}"></div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-title">
            <div><h3>اختيار الاستحقاق</h3><div class="help">اختر من دفعات العقد أو الصيانة أو الاشتراك أو دفعات ملحقات هذا العقد فقط. يجب توزيع قيمة السند بالكامل.</div></div>
        </div>
        <div data-allocation-rows>
            @php($oldAllocations=old('allocations',$editing?$collection->allocations->map(fn($a)=>['receivable_id'=>$a->receivable_id,'amount'=>$a->amount])->all():[]))
            @if(count($oldAllocations))
                @foreach($oldAllocations as $i=>$a)
                    @php($r=$receivables->firstWhere('id',(int)$a['receivable_id']))
                    <div class="allocation-row receivable-choice">
                        <div>
                            <div class="inline-actions"><span class="badge badge-info">{{ $receivableTypes[$r?->type]??'استحقاق' }}</span><strong>{{ $r?->name??'استحقاق' }}</strong></div>
                            <small>{{ $r?->due_date?->format('Y-m-d') }} · المتبقي {{ number_format((float)($r?->remaining_amount??0)+(float)($editing?($a['amount']??0):0),2) }}</small>
                        </div>
                        <input type="hidden" name="allocations[{{ $i }}][receivable_id]" value="{{ $a['receivable_id'] }}">
                        <div class="allocation-amount-control"><input class="input" type="number" min="0" step=".01" name="allocations[{{ $i }}][amount]" value="{{ $a['amount'] }}"><button class="btn btn-sm btn-light" type="button" data-fill-receivable data-remaining="{{ (float)($r?->remaining_amount??0)+(float)($editing?($a['amount']??0):0) }}">سداد كامل</button></div>
                    </div>
                @endforeach
            @else
                <div class="empty-state">اختر العميل ثم العقد لعرض الاستحقاقات المفتوحة.</div>
            @endif
        </div>
    </div>

    <div class="form-actions"><button class="btn btn-primary">{{ $editing?'حفظ التعديل':'تأكيد سند القبض' }}</button><a class="btn btn-light" href="{{ route('collections.index') }}">إلغاء</a></div>
</form>
@endsection
