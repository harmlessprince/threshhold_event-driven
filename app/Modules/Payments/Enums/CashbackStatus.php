<?php

namespace App\Modules\Payments\Enums;

enum CashbackStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
