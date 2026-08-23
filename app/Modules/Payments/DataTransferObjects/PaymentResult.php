<?php

namespace App\Modules\Payments\DataTransferObjects;

class PaymentResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
    ) {}

    public static function success(string $providerReference): self
    {
        return new self(successful: true, providerReference: $providerReference);
    }

    public static function failure(string $message): self
    {
        return new self(successful: false, message: $message);
    }
}
