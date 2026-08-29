<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isClient()
            || $user->isSupportAgent();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isSupportAgent()) {
            return true;
        }

        return $user->isClient()
            && $user->organization_id !== null
            && $user->organization_id === $ticket->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->isClient()
            && $user->organization_id !== null;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isSupportAgent();
    }

    public function addReply(User $user, Ticket $ticket): bool
    {
        if ($user->isSupportAgent()) {
            return true;
        }

        return $user->isClient()
            && $user->organization_id !== null
            && $user->organization_id === $ticket->organization_id;
    }

    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isSupportAgent();
    }
}
