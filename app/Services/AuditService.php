<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService { public function log(string $action, string $module, ?string $description = null, array $old = [], array $new = [], ?Request $request = null): AuditLog { return AuditLog::create(['user_id'=>auth()->id(),'action'=>$action,'module'=>$module,'description'=>$description,'old_values'=>$old ?: null,'new_values'=>$new ?: null,'ip_address'=>$request?->ip(),'user_agent'=>$request?->userAgent(),'created_at'=>now()]); } }
