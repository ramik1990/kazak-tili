<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'roman' => $this->roman,
            'title' => $this->title,
            'lessons_range' => $this->lessons_range,
            'goal' => $this->goal,
            'objectives' => $this->objectives ?? [],
            'outcomes' => $this->outcomes ?? [],
            'methods' => $this->methods ?? [],
            'cover' => $this->cover ? "/illustrations/{$this->cover}" : null,
            'accent' => $this->accent,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
