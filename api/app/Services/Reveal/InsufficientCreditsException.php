<?php

declare(strict_types=1);

namespace App\Services\Reveal;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** 402 Payment Required — رصيد الاشتراك نافد. */
final class InsufficientCreditsException extends HttpException
{
    public function __construct(public readonly int $companyProfileId)
    {
        parent::__construct(402, 'رصيد عمليات الكشف نافد.');
    }
}
