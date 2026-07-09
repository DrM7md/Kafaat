<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentIntent
{
    public function __construct(
        public string $providerRef,
        public string $paymentUrl,
    ) {}
}
