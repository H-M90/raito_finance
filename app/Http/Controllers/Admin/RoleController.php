<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class RoleController extends Controller
{
    public function index(): View { return view('admin.roles.index',['roles'=>Role::with('permissions')->orderBy('name')->get(),'permissions'=>Permission::orderBy('group_name')->orderBy('code')->get()->groupBy('group_name')]); }
    public function update(Request $r,Role $role): RedirectResponse { abort_if($role->code==='admin',422,'لا يمكن تقليل صلاحيات مدير النظام.');$data=$r->validate(['name'=>'required|string|max:255','permission_ids'=>'nullable|array','permission_ids.*'=>'exists:permissions,id']);$role->update(['name'=>$data['name']]);$role->permissions()->sync($data['permission_ids']??[]);return back()->with('success','تم تحديث الدور.'); }
}
