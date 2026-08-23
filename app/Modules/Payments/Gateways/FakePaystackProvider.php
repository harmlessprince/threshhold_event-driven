<?php

namespace App\Modules\Payments\Gateways;

use App\Models\User;
use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DataTransferObjects\PaymentResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stub provider standing in for a real Paystack (or other local provider) client.
 * Swap the `payments.provider` config binding for a real client to go live —
 * nothing else in the Payments module needs to change.
 */
class FakePaystackProvider implements PaymentProviderInterface
{
    public function disburse(User $user, int $amountInKobo, string $reference): PaymentResult
    {
        Log::info('FakePaystackProvider: disbursing cashback', [
            'user_id' => $user->id,
            'amount_in_kobo' => $amountInKobo,
            'reference' => $reference,
        ]);

        return PaymentResult::success(Str::uuid()->toString());
    }
}
