<?php

declare(strict_types=1);

namespace App\Services\Reveal;

use App\Models\CandidateReveal;

final readonly class RevealResult
{
    private function __construct(
        public CandidateReveal $reveal,
        public string $outcome,          // already_revealed | completed | awaiting_payment
        public ?string $paymentUrl = null,
    ) {}

    /** كشفٌ سابق صالح — لا خصم جديد. */
    public static function alreadyRevealed(CandidateReveal $reveal): self
    {
        return new self($reveal, 'already_revealed');
    }

    /** خُصم من رصيد الاشتراك، أو منحة إدارية — فُكّ الحجب فوراً. */
    public static function completed(CandidateReveal $reveal): self
    {
        return new self($reveal, 'completed');
    }

    /** بانتظار الدفع — الحجب **لم** يُفكّ بعد. */
    public static function awaitingPayment(CandidateReveal $reveal, string $paymentUrl): self
    {
        return new self($reveal, 'awaiting_payment', $paymentUrl);
    }

    public function httpStatus(): int
    {
        return match ($this->outcome) {
            'already_revealed' => 200,
            'completed' => 201,
            default => 202,
        };
    }
}
