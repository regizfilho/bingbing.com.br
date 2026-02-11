<?php

namespace App\Models\Ranking;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Title extends Model
{
    protected $fillable = ['user_id', 'type', 'earned_at'];
    
    protected $casts = ['earned_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}