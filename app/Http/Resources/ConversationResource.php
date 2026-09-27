<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'conversation'=>$this->id,
            'sender_id'=>$this->sender_id,
            'receiver_id'=>$this->receiver_id,
            'conversation_name'=>$this->receiver->name,
            'receiver_number'=>$this->receiver->phone,
            'messages'=>$this->messages
        ];
    }
}
