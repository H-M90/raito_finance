@extends('layouts.app')

@section('title', 'سداد استحقاق')
@section('page-title', 'تسجيل سداد استحقاق')
@section('page-subtitle', 'سيتم إنشاء سند قبض وربطه بهذا الاستحقاق فقط')

@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header">
        <div>
            <h2>{{ $receivable->number }}</h2>
            <p>{{ $receivable->customer->name }} · {{ $receivable->contract?->number }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('receivables.index', ['q' => $receivable->number]) }}">رجوع</a>
    </div>

    @if ($errors->has('receivable'))
        <div class="alert alert-error">{{ $errors->first('receivable') }}</div>
    @endif

    <div class="detail-list" style="margin-bottom:18px">
        <div class="detail-item"><span>بيان الاستحقاق</span><strong>{{ $receivable->name }}</strong></div>
        <div class="detail-item"><span>إجمالي الاستحقاق</span><strong>{{ number_format((float) $receivable->total_amount, 2) }} {{ $receivable->currency }}</strong></div>
        <div class="detail-item"><span>الرصيد المتبقي</span><strong class="text-danger">{{ number_format((float) $receivable->remaining_amount, 2) }} {{ $receivable->currency }}</strong></div>
    </div>

    <form method="post" action="{{ route('receivables.payment.store', $receivable) }}">
        @csrf
        <div class="form-grid">
            <div class="form-group col-6">
                <label for="collection_date">تاريخ السداد</label>
                <input id="collection_date" class="input" type="date" name="collection_date" value="{{ old('collection_date', today()->format('Y-m-d')) }}" required>
                @error('collection_date')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group col-6">
                <label for="amount">قيمة السداد ({{ $receivable->currency }})</label>
                <input id="amount" class="input" type="number" name="amount" value="{{ old('amount', number_format((float) $receivable->remaining_amount, 2, '.', '')) }}" min="0.01" max="{{ number_format((float) $receivable->remaining_amount, 2, '.', '') }}" step="0.01" required>
                @error('amount')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
        </div>
        <p class="text-muted" style="margin:12px 0">سيُنشأ سند قبض مستقل بالسداد المحدد، ثم يُحدّث المدفوع والمتبقي وقيد حساب العميل تلقائيًا.</p>
        <div class="inline-actions">
            <button class="btn btn-primary">تسجيل السداد</button>
            <a class="btn btn-light" href="{{ route('receivables.index', ['q' => $receivable->number]) }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
