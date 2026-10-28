<?php

namespace App\Enums;

enum AttemptTrigger: string
{
    case Initial = 'initial';
    case Retry = 'retry';
}
