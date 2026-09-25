<?php

namespace App\Enums;

enum SenderType: string
{
    case Customer = 'customer';
    case Bot = 'bot';
    case Human = 'human';
}
