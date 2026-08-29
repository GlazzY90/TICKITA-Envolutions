<?php

namespace App\Enums;

enum MessageVisibility: string
{
    case CLIENT_VISIBLE = 'public';
    case INTERNAL = 'internal';
}
