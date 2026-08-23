<?php

use App\Modules\Payments\Gateways\FakePaystackProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Provider
    |--------------------------------------------------------------------------
    |
    | Concrete class bound to PaymentProviderInterface. Swap this for a real
    | Paystack (or other local provider) client to go live.
    |
    */

    'provider' => env('PAYMENT_PROVIDER', FakePaystackProvider::class),

    /*
    |--------------------------------------------------------------------------
    | Badge Cashback Amount
    |--------------------------------------------------------------------------
    |
    | The cashback paid out per unlocked badge, in kobo. The assessment spec
    | fixes this at 300 Naira (30,000 kobo).
    |
    */

    'badge_cashback_amount' => (int) env('BADGE_CASHBACK_AMOUNT_KOBO', 30000),
];
