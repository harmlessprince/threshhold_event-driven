<?php

use App\Models\User;
use App\Modules\Badges\Events\BadgeUnlocked;
use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DataTransferObjects\PaymentResult;
use App\Modules\Payments\Enums\CashbackStatus;
use App\Modules\Payments\Models\CashbackTransaction;

test('unlocking a badge creates a successful cashback transaction for the configured amount', function () {
    $user = User::factory()->create();

    BadgeUnlocked::dispatch('Beginner', $user, 1);

    $transaction = CashbackTransaction::query()
        ->where('user_id', $user->id)
        ->where('badge_id', 1)
        ->firstOrFail();

    expect($transaction->status)->toBe(CashbackStatus::Success);
    expect($transaction->amount)->toBe((int) config('payments.badge_cashback_amount'));
    expect($transaction->provider_reference)->not->toBeNull();
});

test('a failed payment marks the cashback transaction as failed, not silently dropped', function () {
    $this->app->bind(PaymentProviderInterface::class, fn () => new class implements PaymentProviderInterface
    {
        public function disburse(User $user, int $amountInKobo, string $reference): PaymentResult
        {
            return PaymentResult::failure('provider unreachable');
        }
    });

    $user = User::factory()->create();

    BadgeUnlocked::dispatch('Beginner', $user, 1);

    $transaction = CashbackTransaction::query()
        ->where('user_id', $user->id)
        ->where('badge_id', 1)
        ->firstOrFail();

    expect($transaction->status)->toBe(CashbackStatus::Failed);
});

test('a provider exception marks the cashback transaction as failed, not stuck pending', function () {
    $this->app->bind(PaymentProviderInterface::class, fn () => new class implements PaymentProviderInterface
    {
        public function disburse(User $user, int $amountInKobo, string $reference): PaymentResult
        {
            throw new RuntimeException('connection timed out');
        }
    });

    $user = User::factory()->create();

    BadgeUnlocked::dispatch('Beginner', $user, 1);

    $transaction = CashbackTransaction::query()
        ->where('user_id', $user->id)
        ->where('badge_id', 1)
        ->firstOrFail();

    expect($transaction->status)->toBe(CashbackStatus::Failed);
});

test('firing BadgeUnlocked twice for the same badge does not call the provider twice or double-pay', function () {
    $provider = Mockery::mock(PaymentProviderInterface::class);
    $provider->shouldReceive('disburse')->once()->andReturn(PaymentResult::success('ref-1'));

    $this->app->instance(PaymentProviderInterface::class, $provider);

    $user = User::factory()->create();

    BadgeUnlocked::dispatch('Beginner', $user, 1);
    BadgeUnlocked::dispatch('Beginner', $user, 1);

    expect(CashbackTransaction::query()
        ->where('user_id', $user->id)
        ->where('badge_id', 1)
        ->count())->toBe(1);
});
