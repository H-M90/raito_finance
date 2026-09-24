@extends('layouts.app')
@section('title','سجل التدقيق')
@section('page-title','سجل التدقيق')
@section('page-subtitle','من غيّر ماذا ومتى، مع القيم قبل وبعد')
@section('content')
<form class="toolbar"><select class="select" name="event"><option value="">كل العمليات</option>@foreach(['created'=>'إنشاء','updated'=>'تعديل','deleted'=>'حذف'] as $k=>$v)<option value="{{ $k }}" @selected(request('event')===$k)>{{ $v }}</option>@endforeach</select><button class="btn btn-light">تصفية</button></form>
<div class="card table-wrap"><table><thead><tr><th>التاريخ</th><th>المستخدم</th><th>العملية</th><th>نوع السجل</th><th>رقم السجل</th><th>التغييرات</th><th>IP</th></tr></thead><tbody>@forelse($logs as $l)<tr><td>{{ $l->created_at?->format('Y-m-d H:i') }}</td><td>{{ $l->user?->name??'النظام' }}</td><td>{{ $l->event }}</td><td>{{ class_basename($l->auditable_type) }}</td><td>{{ $l->auditable_id }}</td><td><details><summary>عرض</summary><pre>{{ json_encode(['قبل'=>$l->old_values,'بعد'=>$l->new_values],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre></details></td><td>{{ $l->ip_address }}</td></tr>@empty<tr><td colspan="7" class="empty-state">لا توجد حركات.</td></tr>@endforelse</tbody></table></div>{{ $logs->links() }}
@endsection
