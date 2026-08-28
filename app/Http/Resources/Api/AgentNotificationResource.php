<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentNotificationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'sale_id' => $this->sale_id,
            'is_read' => $this->read_at !== null,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
