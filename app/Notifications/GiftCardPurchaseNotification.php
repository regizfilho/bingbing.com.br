<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Wallet\GiftCard;

class GiftCardPurchaseNotification extends Notification
{
    use Queueable;

    public GiftCard $giftCard;

    public function __construct(GiftCard $giftCard)
    {
        $this->giftCard = $giftCard;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎁 Gift Card Criado - BingBing')
            ->view('emails.gift-card-purchase', [
                'user' => $notifiable,
                'giftCard' => $this->giftCard,
                'code' => $this->giftCard->code,
                'creditValue' => $this->giftCard->credit_value,
                'price' => $this->giftCard->price_brl,
                'expiresAt' => $this->giftCard->expires_at,
            ]);
    }
}