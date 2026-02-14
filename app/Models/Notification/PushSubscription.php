<?php

namespace App\Models\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PushSubscription extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'device_info',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($subscription) {
            if (empty($subscription->uuid)) {
                $subscription->uuid = Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
