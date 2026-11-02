<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $teacher = $request->user()?->isTeacher() ?? false;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'position' => $this->position,
            'scope' => $this->scope,
            'skill' => $this->skill,
            'type' => $this->type,
            'prompt' => $this->prompt,
            'instruction' => $this->instruction,
            'items' => $this->payload['items'] ?? [],
            'options' => $this->payload['options'] ?? [],
            'questions' => $this->questions(),
            'audio' => $this->audio_path ? "/storage/{$this->audio_path}" : null,
            'points' => $this->points,
            'auto_check' => (bool) $this->auto_check,
            'has_key' => $this->answer_key !== null,
            'answer_key' => $this->when($teacher, $this->answer_key),
            'answer' => new AnswerResource($this->whenLoaded('myAnswer')),
        ];
    }
}
