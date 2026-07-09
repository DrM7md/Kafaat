<?php

declare(strict_types=1);

namespace App\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case Hidden = 'hidden';
}
