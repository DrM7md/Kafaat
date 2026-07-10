<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * الدفع قيد التطوير — لم تُربَط بوّابةٌ حقيقية بعد.
 *
 * 503 لا 500: هذه حالةٌ معروفة ومقصودة من النظام، لا انهيار. المستخدم
 * يستحقّ جواباً مفهوماً، والمراقبة تستحقّ ألّا تُوقَظ على خطأٍ ليس خطأً.
 *
 * موضعان يرميانها:
 *   • `ManualGateway` إن شُغّلت في الإنتاج — بوّابةٌ تؤكّد الدفع بلا مال.
 *   • حلّ البوّابة إن اختِيرت بوّابةٌ تجارية لم تُنفَّذ بعد.
 */
final class PaymentsUnderDevelopmentException extends ServiceUnavailableHttpException
{
    public function __construct(string $reason = '')
    {
        parent::__construct(retryAfter: null, message: trim(
            'الدفع قيد التطوير حالياً. سيُفعَّل عند ربط بوّابة الدفع. '.$reason
        ));
    }
}
