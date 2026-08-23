<?php

namespace App\Modules\Payments\Contracts;

use App\Models\User;
use App\Modules\Payments\DataTransferObjects\PaymentResult;

interface PaymentProviderInterface
{
    public function disburse(User $user, int $amountInKobo, string $reference): PaymentResult;
}
