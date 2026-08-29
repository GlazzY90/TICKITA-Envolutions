<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
Logic:
Validates supported ticket-list search/filter parameters.

Structure:
Validation belongs in a Form Request instead of TicketController,
keeping the controller focused on application flow.

DSA:
No custom DSA. MySQL performs filtering. Exact filters can use
database indexes; "%term%" search may require scanning candidate rows.
*/
class IndexTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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

            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}