<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'payload' => $this->payload,
            'status' => $this->status,
            'score' => $this->score,
            'max_score' => $this->max_score,
            'attempts' => $this->attempts,
            'feedback' => $this->feedback,
            'checked_at' => $this->checked_at,
            'submitted_at' => $this->submitted_at,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'task' => new TaskResource($this->whenLoaded('task')),
        ];
    }
}
