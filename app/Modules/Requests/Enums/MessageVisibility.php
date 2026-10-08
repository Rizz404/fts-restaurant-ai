<?php

namespace App\Modules\Requests\Enums;

enum MessageVisibility: string
{
    case Public = 'public';
    case Internal = 'internal';
}
