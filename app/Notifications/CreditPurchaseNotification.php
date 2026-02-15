<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Wallet\Transaction;

class CreditPurchaseNotification extends Notification
{
    use Queueable;

    public Transaction $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 Recarga Confirmada - BingBing')
            ->view('emails.credit-purchase', [
                'user' => $notifiable,
                'transaction' => $this->transaction,
                'credits' => $this->transaction->amount,
                'packageName' => $this->transaction->description,
                'originalAmount' => $this->transaction->original_amount,
                'discountAmount' => $this->transaction->discount_amount,
                'finalAmount' => $this->transaction->final_amount,
                'newBalance' => $this->transaction->balance_after,
                'hasCoupon' => $this->transaction->discount_amount > 0,
            ]);
    }
}