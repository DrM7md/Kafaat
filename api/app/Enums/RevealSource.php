<?php

declare(strict_types=1);

namespace App\Enums;

enum RevealSource: string
{
    case Payment = 'payment';
    case Subscription = 'subscription';

    /** منحة إدارية من المشرف — بلا دفع، وتُسجَّل في التدقيق. */
    case Granted = 'granted';
}
