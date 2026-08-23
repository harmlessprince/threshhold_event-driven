<?php

namespace App\Modules\Payments\Listeners;

use App\Modules\Badges\Events\BadgeUnlocked;
use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\Enums\CashbackStatus;
use App\Modules\Payments\Models\CashbackTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class TriggerCashback implements ShouldQueue
{
    public function __construct(private readonly PaymentProviderInterface $provider) {}

    public function handle(BadgeUnlocked $event): void
    {
        $amount = (int) config('payments.badge_cashback_amount');

        try {
            $transaction = CashbackTransaction::query()->create([
                'user_id' => $event->user->id,
                'badge_id' => $event->badge_id,
                'amount' => $amount,
                'provider' => config('payments.provider'),
                'status' => CashbackStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already recorded for this user/badge — a redelivered BadgeUnlocked is a no-op.
            return;
        }

        $result = $this->provider->disburse($event->user, $amount, (string) Str::uuid());

        $transaction->update([
            'status' => $result->successful ? CashbackStatus::Success : CashbackStatus::Failed,
            'provider_reference' => $result->providerReference,
        ]);
    }
}
