<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\MapLocationService;

class VehicleLocationController extends Controller
{
    public function index(MapLocationService $mapLocations)
    {
        return $mapLocations->data();
    }

    public function inventory(Vehicle $vehicle)
    {
        return $vehicle->inventory()->with('item.category')->get();
    }
}
