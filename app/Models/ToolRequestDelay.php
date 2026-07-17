<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolRequestDelay extends Model
{
    protected $guarded = [];

    protected $casts = [
        'detected_at' => 'datetime:Y-m-d H:i:s',
        'resolved_at' => 'datetime:Y-m-d H:i:s',
        'state_started_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function request()
    {
        return $this->belongsTo(ToolRequest::class, 'tool_request_id');
    }
}
