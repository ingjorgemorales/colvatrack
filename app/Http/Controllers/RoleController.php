<?php
namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Roles/Index', ['roles' => Role::withCount('users')->with('permissions')->orderBy('name')->get()]);
    }

    public function create()
    {
        return Inertia::render('Roles/Form', ['role' => null, 'permissions' => $this->permissions()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required','string','max:120','unique:roles,name'], 'description' => ['nullable','string'], 'permissions' => ['array'], 'permissions.*' => ['exists:permissions,id']]);
        $permissions = $data['permissions'] ?? []; unset($data['permissions']);
        $role = Role::create($data); $role->permissions()->sync($permissions);
        return redirect()->route('roles.index')->with('success', 'Rol creado.');
    }

    public function edit(Role $role)
    {
        return Inertia::render('Roles/Form', ['role' => $role->load('permissions'), 'permissions' => $this->permissions()]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate(['name' => ['required','string','max:120', Rule::unique('roles','name')->ignore($role->id)], 'description' => ['nullable','string'], 'permissions' => ['array'], 'permissions.*' => ['exists:permissions,id']]);
        $permissions = $data['permissions'] ?? []; unset($data['permissions']);
        $role->update($data); $role->permissions()->sync($permissions);
        return redirect()->route('roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroy(Role $role)
    {
        abort_if(in_array($role->name, ['Administrador','Tecnico','Conductor'], true), 422, 'No se pueden eliminar roles base.');
        $role->delete(); return back()->with('success', 'Rol eliminado.');
    }

    private function permissions()
    {
        return Permission::orderBy('module')->orderBy('action')->get()->groupBy('module');
    }
}
