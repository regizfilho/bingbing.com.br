<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = 'wallet_refunds';

    protected $fillable = [
        'user_id',
        'transaction_id',
        'amount_brl',
        'credits',
        'status',
        'reason',
        'admin_note',
        'approved_by',
        'approved_at'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(\App\Models\Wallet\Transaction::class);
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
}
