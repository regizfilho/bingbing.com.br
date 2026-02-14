<?php

namespace App\Models\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PushNotificationClick extends Model
{
    protected $fillable = [
        'uuid',
        'push_notification_id',
        'user_id',
        'push_subscription_id',
        'action',
        'metadata',
        'clicked_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'clicked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($click) {
            if (empty($click->uuid)) {
                $click->uuid = Str::uuid();
            }
        });
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class, 'push_notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'push_subscription_id');
    }
}