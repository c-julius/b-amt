<?php

namespace App\Enums;

enum OrderSource: string
{
    case ONLINE = 'online';
    case POS = 'pos';
}
