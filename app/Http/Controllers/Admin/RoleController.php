<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $catalog = PermissionCatalog::groups();
        $allPermissions = Permission::query()->get();
        $permissions = collect();

        foreach ($catalog as $groupCode => $groupMeta) {
            $order = array_flip(array_keys($groupMeta['permissions']));
            $items = $allPermissions
                ->where('group_name', $groupCode)
                ->sortBy(fn (Permission $permission) => $order[$permission->code] ?? PHP_INT_MAX)
                ->values();

            if ($items->isNotEmpty()) {
                $permissions->put($groupCode, $items);
            }
        }

        // Keep any future/custom permissions visible even before the catalogue is updated.
        $knownGroups = array_keys($catalog);
        $allPermissions
            ->whereNotIn('group_name', $knownGroups)
            ->groupBy('group_name')
            ->sortKeys()
            ->each(fn ($items, $group) => $permissions->put($group, $items->sortBy('name')->values()));

        $roles = Role::with('permissions')
            ->get()
            ->sortBy(fn (Role $role) => $role->code === 'admin' ? '0' : '1'.$role->name)
            ->values();

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'permissionCatalog' => $catalog,
            'permissionTotal' => $allPermissions->count(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->code === 'admin', 422, 'لا يمكن تقليل صلاحيات مدير النظام.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return back()->with('success', 'تم تحديث الدور والصلاحيات بنجاح.');
    }
}
