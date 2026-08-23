<?php

namespace App\Modules\Payments\Listeners;

use App\Modules\Badges\Events\BadgeUnlocked;
use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\Enums\CashbackStatus;
use App\Modules\Payments\Models\CashbackTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Throwable;

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

        // A thrown exception (network error, provider outage) is treated the same as an
        // explicit decline: the row must not be left `pending` forever, since a redelivery
        // would just hit the unique-constraint guard above and no-op without ever
        // resolving it. See README "What I Deliberately Did Not Build" for why this
        // collapses "provider declined" and "we couldn't confirm the outcome" into the
        // same status rather than distinguishing them.
        try {
            $result = $this->provider->disburse($event->user, $amount, (string) Str::uuid());
        } catch (Throwable $exception) {
            report($exception);

            $transaction->update(['status' => CashbackStatus::Failed]);

            return;
        }

        $transaction->update([
            'status' => $result->successful ? CashbackStatus::Success : CashbackStatus::Failed,
            'provider_reference' => $result->providerReference,
        ]);
    }
}
