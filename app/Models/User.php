<?php

namespace App\Models;

use App\Notifications\ColvatrackResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedVehicle()
    {
        return $this->hasOne(Vehicle::class, 'driver_id');
    }

    public function locationLogs()
    {
        return $this->hasMany(UserLocationLog::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->role && in_array($this->role->name, $roles, true);
    }

    public function canAccess(string $module, string $action = 'ver'): bool
    {
        if ($this->hasRole('Administrador')) {
            return true;
        }

        return (bool) $this->role?->permissions
            ->where('module', $module)
            ->where('action', $action)
            ->isNotEmpty();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ColvatrackResetPassword($token));
    }
}
