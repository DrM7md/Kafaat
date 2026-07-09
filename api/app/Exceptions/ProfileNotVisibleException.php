<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ADR-004 — يُعاد 404، لا 403.
 *
 * الشركة يجب ألّا تعلم بوجود ملفٍّ لموظّفها الحالي. و`403` تعني
 * «موجود لكن ممنوع» — وهذا وحده يخبرها أن موظّفها يبحث عن عمل.
 *
 * **الوجود نفسه هو السرّ.** أخطر ضرر بشري يمكن أن يسبّبه هذا المنتج.
 */
final class ProfileNotVisibleException extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('غير موجود.');
    }
}
