<?php

namespace App\Enums;

enum AttemptResult: string
{
    case Success = 'success';
    case Failed = 'failed';
}
