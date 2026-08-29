<?php

namespace App\Http\Requests\Ticket;

use App\Enums\MessageVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
Logic:
Validates ticket conversation messages.

Structure:
A single endpoint handles public replies and internal notes.
Authorization decides whether the requested visibility is permitted.

DSA:
No custom DSA. Validation operates on two fields: O(1).
*/
class StoreTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:10000',
            ],

            'visibility' => [
                'sometimes',
                Rule::enum(MessageVisibility::class),
            ],
        ];
    }
}