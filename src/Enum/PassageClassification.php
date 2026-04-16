<?php

namespace App\Enum;

enum PassageClassification: string
{
    case ON_TIME = 'ON_TIME';
    case LESS_THAN_15 = 'LESS_THAN_15';
    case MORE_THAN_15 = 'MORE_THAN_15';
    case CANCELLED = 'CANCELLED';
}
