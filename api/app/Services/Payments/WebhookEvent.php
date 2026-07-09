<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class WebhookEvent
{
    public function __construct(
        public string $type,          // payment.succeeded | payment.failed
        public string $providerRef,
        public int $amount,
    ) {}

    public function succeeded(): bool
    {
        return $this->type === 'payment.succeeded';
    }
}
