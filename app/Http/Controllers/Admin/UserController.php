<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
class UserController extends Controller
{
    public function index(): View { return view('admin.users.index',['users'=>User::with('role')->orderBy('name')->paginate(30),'roles'=>Role::orderBy('name')->get()]); }
    public function store(Request $r): RedirectResponse { $data=$r->validate(['name'=>'required|string|max:255','email'=>'required|email|unique:users,email','password'=>'required|string|min:8','role_id'=>'required|exists:roles,id','is_active'=>'nullable|boolean']);User::create($data+['is_active'=>$r->boolean('is_active',true)]);return back()->with('success','تم إنشاء المستخدم.'); }
    public function update(Request $r,User $user): RedirectResponse { $data=$r->validate(['name'=>'required|string|max:255','email'=>['required','email',Rule::unique('users','email')->ignore($user->id)],'password'=>'nullable|string|min:8','role_id'=>'required|exists:roles,id','is_active'=>'nullable|boolean']);if(blank($data['password']??null))unset($data['password']);$data['is_active']=$r->boolean('is_active');$user->update($data);return back()->with('success','تم تحديث المستخدم وصلاحياته.'); }
}
