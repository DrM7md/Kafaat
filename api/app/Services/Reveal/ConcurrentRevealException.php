<?php

declare(strict_types=1);

namespace App\Services\Reveal;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 409 Conflict — طلبٌ متزامن سبق هذا الطلب إلى حجز خانة الكشف.
 *
 * وصولنا إلى هنا يعني أن الفهرس الفريد أدّى دوره: لم يقع خصم مزدوج.
 */
final class ConcurrentRevealException extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'طلب كشفٍ آخر لهذا المرشّح قيد المعالجة.');
    }
}
