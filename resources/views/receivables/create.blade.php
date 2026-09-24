@extends('layouts.app')

@section('title', 'إضافة استحقاق')
@section('page-title', 'إضافة استحقاق يدوي')
@section('page-subtitle', 'ينشئ النظام استحقاقًا وقيدًا محاسبيًا مرتبطين بالعقد المختار')

@section('content')
<div class="card" style="max-width:860px">
    <div class="card-header">
        <div><h2>استحقاق جديد</h2><p>تُستمد العملة والعميل من العقد تلقائيًا.</p></div>
        <a class="btn btn-light" href="{{ route('receivables.index') }}">رجوع</a>
    </div>

    @if ($errors->has('receivable'))
        <div class="alert alert-error">{{ $errors->first('receivable') }}</div>
    @endif

    <form method="post" action="{{ route('receivables.store') }}">
        @csrf
        <div class="form-grid">
            <div class="form-group col-12">
                <label for="contract_id">العقد</label>
                <select id="contract_id" class="select" name="contract_id" required>
                    <option value="">اختر العقد</option>
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected((int) old('contract_id') === $contract->id)>{{ $contract->number }} · {{ $contract->customer->name }} · {{ $contract->currency }}</option>
                    @endforeach
                </select>
                @error('contract_id')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group col-8">
                <label for="name">بيان الاستحقاق</label>
                <input id="name" class="input" name="name" value="{{ old('name') }}" maxlength="255" required>
                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group col-4">
                <label for="due_date">تاريخ الاستحقاق</label>
                <input id="due_date" class="input" type="date" name="due_date" value="{{ old('due_date', today()->format('Y-m-d')) }}" required>
                @error('due_date')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group col-4">
                <label for="total_amount">قيمة الاستحقاق</label>
                <input id="total_amount" class="input" type="number" name="total_amount" value="{{ old('total_amount') }}" min="0.01" step="0.01" required>
                @error('total_amount')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group col-8">
                <label for="notes">ملاحظات</label>
                <input id="notes" class="input" name="notes" value="{{ old('notes') }}">
                @error('notes')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
        </div>
        <p class="text-muted" style="margin:12px 0">تُسجّل القيمة كإجمالي استحقاق يدوي؛ لا تُضاف عليها ضريبة تلقائية.</p>
        <div class="inline-actions"><button class="btn btn-primary">إضافة الاستحقاق</button><a class="btn btn-light" href="{{ route('receivables.index') }}">إلغاء</a></div>
    </form>
</div>
@endsection
