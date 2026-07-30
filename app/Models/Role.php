<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function manageableRoles()
    {
        return $this->belongsToMany(Role::class, 'role_manageable_roles', 'role_id', 'manageable_role_id');
    }
}
