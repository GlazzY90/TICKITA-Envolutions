<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
Logic:
Defines the JSON representation of a conversation message.

Structure:
Serialization is separate from the model/controller so the API response
format is explicit and consistent.

DSA:
No algorithm. Transforming one message is O(1).
A collection of m messages is O(m).
*/
class TicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'visibility' => $this->visibility->value,

            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'role' => $this->author->role->value,
            ],

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
