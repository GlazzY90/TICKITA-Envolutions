<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
Logic:
Validates client-provided values when creating a ticket.

Structure:
The client is allowed to provide only title, description, and priority.
Organization, creator, status, and SLA fields are determined by Laravel.

DSA:
No algorithm is used. Validation runs over a fixed number of fields: O(1).
*/
class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'max:10000',
            ],

            'priority' => [
                'required',
                Rule::enum(TicketPriority::class),
            ],
        ];
    }
}