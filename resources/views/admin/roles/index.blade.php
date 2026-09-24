@extends('layouts.app')
@section('title','الأدوار والصلاحيات')
@section('page-title','الأدوار والصلاحيات')
@section('page-subtitle','صلاحيات تفصيلية لكل شاشة وعملية')
@section('content')
@php $permissionGroupLabels=['customer-success'=>'متابعة العملاء']; @endphp
@foreach($roles as $role)<form method="post" action="{{ route('admin.roles.update',$role) }}" class="form-section">@csrf @method('put')<div class="form-section-title"><h3>{{ $role->name }}</h3>@if($role->code!=='admin')<button class="btn btn-sm btn-primary">حفظ الدور</button>@else<span class="badge badge-info">كل الصلاحيات</span>@endif</div><input class="input" name="name" value="{{ $role->name }}" {{ $role->code==='admin'?'readonly':'' }}>@foreach($permissions as $group=>$items)<h4>{{ $permissionGroupLabels[$group] ?? $group }}</h4><div class="permission-grid">@foreach($items as $p)<label class="check-card"><input type="checkbox" name="permission_ids[]" value="{{ $p->id }}" @checked($role->code==='admin'||$role->permissions->contains($p)) @disabled($role->code==='admin')><span>{{ $p->name }}</span></label>@endforeach</div>@endforeach</form>@endforeach
@endsection
