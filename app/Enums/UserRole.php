<?php

namespace App\Enums;

enum UserRole: string
{
    case CLIENT = 'client';
    case SUPPORT_AGENT = 'support_agent';
}
