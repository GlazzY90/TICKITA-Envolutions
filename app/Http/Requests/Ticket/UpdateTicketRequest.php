<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
Logic:
Validates agent-controlled ticket updates.

Structure:
Only status, current priority, and assignment are mutable through this
endpoint. Authorization remains in TicketPolicy.

DSA:
No custom DSA. Validation is O(1). Agent existence is delegated
to an indexed database lookup.
*/
class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                Rule::enum(TicketStatus::class),
            ],

            'priority' => [
                'sometimes',
                Rule::enum(TicketPriority::class),
            ],

            'assigned_to' => [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'role',
                            UserRole::SUPPORT_AGENT->value
                        )
                    ),
            ],
        ];
    }
}
