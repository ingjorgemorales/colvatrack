<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_manageable_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manageable_role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'manageable_role_id'], 'role_manageable_roles_unique');
        });

        $this->grantDefaultScopes();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_manageable_roles');
    }

    private function grantDefaultScopes(): void
    {
        $roles = Role::query()->get()->keyBy('name');
        $superadmin = $roles->get('Superadministrador');

        if ($superadmin) {
            $superadmin->manageableRoles()->syncWithoutDetaching(
                $roles->reject(fn (Role $role) => $role->name === 'Superadministrador')->pluck('id')->all()
            );
        }

        $this->grant($roles, 'Administrador', ['Administrador1', 'Administrador2', 'Administrador', 'Auditor', 'Conductor', 'Tecnico']);
        $this->grant($roles, 'Administrador1', ['Conductor']);
        $this->grant($roles, 'Administrador2', ['Tecnico']);
    }

    private function grant($roles, string $roleName, array $manageableNames): void
    {
        $role = $roles->get($roleName);

        if (! $role) {
            return;
        }

        $role->manageableRoles()->syncWithoutDetaching(
            $roles->only($manageableNames)->pluck('id')->all()
        );
    }
};
