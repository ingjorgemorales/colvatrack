<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Notification extends Model
{
    protected $guarded = [];

    protected $casts = ['data_json'=>'array','read_at'=>'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function url(): ?string
    {
        return match ($this->type) {
            'tool_request', 'tool_request_status', 'tool_request_delay', 'chat' => $this->data_json ? '/solicitudes/'.$this->data_json['tool_request_id'] : null,
            'gps_stale_summary' => '/mapa',
            'request_delay_summary' => '/solicitudes',
            'low_stock_summary' => '/inventario',
            default => null,
        };
    }
}
