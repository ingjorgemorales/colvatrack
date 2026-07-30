<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_gps_datetime' => 'datetime:Y-m-d H:i:s',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
    ];

    public function provider()
    {
        return $this->belongsTo(GpsProvider::class, 'gps_provider_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function locations()
    {
        return $this->hasMany(VehicleLocation::class);
    }

    public function inventory()
    {
        return $this->hasMany(VehicleInventory::class);
    }

    public function reservations()
    {
        return $this->hasMany(VehicleReservation::class);
    }

    public function toolRequests()
    {
        return $this->hasMany(ToolRequest::class);
    }

    public function activeToolRequest()
    {
        return $this->hasOne(ToolRequest::class)->activeForVehicle()->latestOfMany();
    }

    public function activeReservation()
    {
        return $this->hasOne(VehicleReservation::class)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latestOfMany();
    }

    public function scopeGpsRecent(Builder $query, CarbonInterface $freshAfter): Builder
    {
        return $query->whereNotNull('last_gps_datetime')->where('last_gps_datetime', '>=', $freshAfter);
    }

    public function scopeGpsStale(Builder $query, CarbonInterface $freshAfter): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('last_gps_datetime')->orWhere('last_gps_datetime', '<', $freshAfter));
    }

    public function scopeMovingNow(Builder $query, CarbonInterface $freshAfter, float $minSpeed): Builder
    {
        return $query->where('status', 'active')->gpsRecent($freshAfter)->where('current_speed', '>', $minSpeed);
    }

    public function scopeStoppedNow(Builder $query, CarbonInterface $freshAfter, float $minSpeed): Builder
    {
        return $query->where('status', 'active')
            ->gpsRecent($freshAfter)
            ->where(fn (Builder $q) => $q->whereNull('current_speed')->orWhere('current_speed', '<=', $minSpeed));
    }

    public function scopeGpsStaleNow(Builder $query, CarbonInterface $freshAfter): Builder
    {
        return $query->where('status', 'active')->gpsStale($freshAfter);
    }

    public function movementStatus(CarbonInterface $freshAfter, float $minSpeed): string
    {
        if (! $this->last_gps_datetime || $this->last_gps_datetime->lessThan($freshAfter)) {
            return 'stale';
        }

        return (float) $this->current_speed > $minSpeed ? 'moving' : 'stopped';
    }
}
