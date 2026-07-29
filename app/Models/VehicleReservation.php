<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleReservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime:Y-m-d H:i:s',
        'ends_at' => 'datetime:Y-m-d H:i:s',
        'released_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function reservedBy()
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }
}
