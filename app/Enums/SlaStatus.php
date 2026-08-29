<?php

namespace App\Enums;

/*
Logic:
Represents the SLA state shown to users.

Structure:
An enum prevents inconsistent strings such as "due-soon",
"due_soon", and "due soon" throughout the application.

DSA:
No algorithm or data structure is used here. Enum lookup is constant-time.
*/
enum SlaStatus: string
{
    case ON_TRACK = 'on_track';
    case DUE_SOON = 'due_soon';
    case OVERDUE = 'overdue';
    case COMPLETED = 'completed';
}