<?php
namespace App\Services;
use App\Events\UserLocationUpdated;
use App\Models\User;
use App\Models\UserLocationLog;
class LocationService { public function update(User $user, float $lat, float $lng, ?float $accuracy = null, string $source = 'web'): User { $user->forceFill(['current_latitude'=>$lat,'current_longitude'=>$lng,'location_updated_at'=>now()])->save(); UserLocationLog::create(['user_id'=>$user->id,'latitude'=>$lat,'longitude'=>$lng,'accuracy'=>$accuracy,'source'=>$source,'created_at'=>now()]); broadcast(new UserLocationUpdated($user))->toOthers(); return $user; } }
