<?php

namespace App\Models\Ranking;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Carbon\Carbon;

class Rank extends Model
{
    protected $fillable = [
        'user_id', 'total_wins', 'weekly_wins', 'monthly_wins',
        'total_games', 'weekly_reset_at', 'monthly_reset_at'
    ];

    protected $casts = [
        'weekly_reset_at' => 'datetime',
        'monthly_reset_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($rank) {
            $rank->weekly_reset_at = Carbon::now()->endOfWeek();
            $rank->monthly_reset_at = Carbon::now()->endOfMonth();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkAndResetPeriods(): void
    {
        if (now()->greaterThan($this->weekly_reset_at)) {
            $this->update([
                'weekly_wins' => 0,
                'weekly_reset_at' => Carbon::now()->endOfWeek(),
            ]);
        }

        if (now()->greaterThan($this->monthly_reset_at)) {
            $this->update([
                'monthly_wins' => 0,
                'monthly_reset_at' => Carbon::now()->endOfMonth(),
            ]);
        }
    }

    public function checkTitles(): void
    {
        $titles = [
            ['type' => 'beginner', 'wins' => 1],
            ['type' => 'experienced', 'wins' => 10],
            ['type' => 'master', 'wins' => 50],
            ['type' => 'legend', 'wins' => 100],
        ];

        foreach ($titles as $title) {
            if ($this->total_wins >= $title['wins']) {
                Title::firstOrCreate([
                    'user_id' => $this->user_id,
                    'type' => $title['type'],
                ], [
                    'earned_at' => now(),
                ]);
            }
        }
    }
}