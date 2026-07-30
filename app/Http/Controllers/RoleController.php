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
        $this->authorizeBaseRoleManagement($role);

        return Inertia::render('Roles/Form', ['role' => $role->load('permissions'), 'permissions' => $this->permissions()]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeBaseRoleManagement($role);

        $data = $request->validate(['name' => ['required','string','max:120', Rule::unique('roles','name')->ignore($role->id)], 'description' => ['nullable','string'], 'permissions' => ['array'], 'permissions.*' => ['exists:permissions,id']]);
        $permissions = $data['permissions'] ?? []; unset($data['permissions']);
        $role->update($data); $role->permissions()->sync($permissions);
        return redirect()->route('roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroy(Role $role)
    {
        abort_if(in_array($role->name, ['Superadministrador','Administrador','Tecnico','Conductor'], true), 422, 'No se pueden eliminar roles base.');
        $role->delete(); return back()->with('success', 'Rol eliminado.');
    }

    private function permissions()
    {
        $catalog = $this->permissionCatalog();

        foreach ($catalog as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['module' => $module, 'action' => $action],
                    ['name' => $module.'.'.$action]
                );
            }
        }

        $permissions = Permission::whereIn('module', array_keys($catalog))
            ->get()
            ->keyBy(fn (Permission $permission) => $permission->module.'.'.$permission->action);

        return collect($catalog)
            ->mapWithKeys(fn (array $actions, string $module) => [
                $module => collect($actions)
                    ->map(fn (string $action) => $permissions->get($module.'.'.$action))
                    ->filter()
                    ->values(),
            ]);
    }

    private function authorizeBaseRoleManagement(Role $role): void
    {
        abort_if(! auth()->user()?->hasRole('Superadministrador') && in_array($role->name, ['Superadministrador','Administrador','Tecnico','Conductor'], true), 403);
    }

    private function permissionCatalog(): array
    {
        return [
            'dashboard' => ['ver'],
            'mapa' => ['ver'],
            'solicitudes' => ['ver', 'crear', 'editar'],
            'chat' => ['ver', 'crear', 'editar'],
            'notificaciones' => ['ver', 'editar', 'gestionar'],
            'inventario' => ['ver', 'crear', 'editar', 'gestionar'],
            'vehiculos' => ['ver', 'crear', 'editar', 'estado', 'recorrido'],
            'proyectos' => ['ver', 'crear', 'editar', 'estado', 'eliminar'],
            'reservas_vehiculos' => ['ver', 'crear', 'editar', 'gestionar'],
            'reportes' => ['ver', 'exportar'],
            'usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
            'roles' => ['ver', 'crear', 'editar', 'eliminar'],
            'auditoria' => ['ver'],
            'perfil' => ['ver', 'editar'],
            'configuracion_gps' => ['ver', 'crear', 'editar', 'eliminar', 'gestionar'],
        ];
    }
}
