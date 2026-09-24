@extends('layouts.app')

@section('title', 'تعديل الاستحقاق المستورد')
@section('page-title', 'تعديل الاستحقاق المستورد')
@section('page-subtitle', 'تحديد القيمة الفعلية للاستحقاق الذي تم استيراده بقيمة صفرية')

@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header">
        <div>
            <h2>{{ $receivable->number }}</h2>
            <p>{{ $receivable->customer->name }} · {{ $receivable->contract?->number ?? 'بدون عقد' }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('receivables.index', ['q' => $receivable->number]) }}">رجوع</a>
    </div>

    @if ($errors->has('receivable'))
        <div class="alert alert-error">{{ $errors->first('receivable') }}</div>
    @endif

    <form method="post" action="{{ route('receivables.update', $receivable) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="form-group col-12">
                <label for="name">بيان الاستحقاق</label>
                <input id="name" class="input" name="name" value="{{ old('name', $receivable->name) }}" required maxlength="255">
                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group col-6">
                <label for="due_date">تاريخ الاستحقاق</label>
                <input id="due_date" class="input" type="date" name="due_date" value="{{ old('due_date', $receivable->due_date->format('Y-m-d')) }}" required>
                @error('due_date')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group col-6">
                <label for="total_amount">قيمة الاستحقاق ({{ $receivable->currency }})</label>
                <input id="total_amount" class="input" type="number" name="total_amount" value="{{ old('total_amount', number_format((float) $receivable->total_amount, 2, '.', '')) }}" min="0.01" step="0.01" required>
                @error('total_amount')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
        </div>
        <p class="text-muted" style="margin:12px 0">سيتم تحديث رصيد العميل تلقائيًا. لا يمكن تعديل الاستحقاق بعد ربطه بتحصيل أو سند خصم.</p>
        <div class="inline-actions">
            <button class="btn btn-primary">حفظ التعديل</button>
            <a class="btn btn-light" href="{{ route('receivables.index', ['q' => $receivable->number]) }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
