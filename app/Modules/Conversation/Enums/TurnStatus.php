<?php

namespace App\Modules\Conversation\Enums;

enum TurnStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
