<?php
namespace App\Services;
use App\Events\NotificationCreated;
use App\Models\Notification;
class NotificationService { public function create(int $userId, string $title, string $message, string $type='info', array $data=[]): Notification { $notification=Notification::create(['user_id'=>$userId,'title'=>$title,'message'=>$message,'type'=>$type,'data_json'=>$data ?: null]); broadcast(new NotificationCreated($notification))->toOthers(); return $notification; } }
