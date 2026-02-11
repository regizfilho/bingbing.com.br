<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance'];
    
    protected $casts = ['balance' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function credit(float $amount, string $description, $transactionable = null)
    {
        return DB::transaction(function () use ($amount, $description, $transactionable) {
            $this->increment('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description,
                'transactionable_type' => $transactionable ? get_class($transactionable) : null,
                'transactionable_id' => $transactionable?->id,
                'status' => 'completed',
            ]);
        });
    }

    public function debit(float $amount, string $description, $transactionable = null)
    {
        return DB::transaction(function () use ($amount, $description, $transactionable) {
            if ($this->balance < $amount) {
                throw new \Exception('Saldo insuficiente');
            }

            $this->decrement('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description,
                'transactionable_type' => $transactionable ? get_class($transactionable) : null,
                'transactionable_id' => $transactionable?->id,
                'status' => 'completed',
            ]);
        });
    }

    public function hasBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}