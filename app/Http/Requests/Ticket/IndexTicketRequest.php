<?php

namespace App\Http\Requests\Ticket;

use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
Logic:
This class is the input boundary for ticket filtering.

Its job is only to answer:
"Is this filter request valid?"

It does NOT:
- decide which tickets the user may see,
- execute filtering,
- query the database.

Structure:
Keeping validation here prevents TicketController and TicketFilter from
being filled with input-validation code.

DSA:
The number of filter fields is fixed, so validation is effectively O(1).
*/
class IndexTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by TicketPolicy in TicketController.
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'organization_id' => [
                'nullable',
                'integer',
                'exists:organizations,id',
            ],

            'status' => [
                'nullable',
                Rule::enum(TicketStatus::class),
            ],

            'priority' => [
                'nullable',
                Rule::enum(TicketPriority::class),
            ],

            'assigned_to' => [
                'nullable',
                'integer',

                Rule::exists('users', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'role',
                            UserRole::SUPPORT_AGENT->value
                        )
                    ),
            ],

            'assignment' => [
                'nullable',
                Rule::in([
                    'assigned',
                    'unassigned',
                ]),
            ],

            'sla_status' => [
                'nullable',
                Rule::enum(SlaStatus::class),
            ],

            'created_from' => [
                'nullable',
                'date',
            ],

            'created_to' => [
                'nullable',
                'date',
                'after_or_equal:created_from',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:10',
                'max:100',
            ],
        ];
    }
}
