<?php
namespace App\Events;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
class UserLocationUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): array
    {
        return [new Channel('users.location')];
    }

    public function broadcastWith(): array
    {
        $this->user->loadMissing('role');
        $maxAge = now()->subMinutes(config('colvatrack.location.max_age_minutes', 1));
        $isFresh = $this->user->location_updated_at?->greaterThan($maxAge) ?? false;

        return [
            'technician' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'role' => $this->user->role?->name,
                'current_latitude' => $this->user->current_latitude,
                'current_longitude' => $this->user->current_longitude,
                'location_updated_at' => $this->user->location_updated_at,
                'location_is_fresh' => $isFresh,
            ],
        ];
    }
}

