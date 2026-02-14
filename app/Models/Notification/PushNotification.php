<?php

namespace App\Models\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PushNotification extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const TARGET_ALL = 'all';
    const TARGET_USER = 'user';
    const TARGET_PLAN = 'plan';

    protected $fillable = [
        'uuid',
        'created_by',
        'title',
        'body',
        'icon',
        'badge',
        'url',
        'data',
        'target_type',
        'target_filters',
        'total_sent',
        'total_success',
        'total_failed',
        'scheduled_at',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'data' => 'array',
        'target_filters' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($notification) {
            if (empty($notification->uuid)) {
                $notification->uuid = Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
?>