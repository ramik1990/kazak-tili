<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'number' => $this->number,
            'title' => $this->title,
            'goal' => $this->goal,
            'outcomes' => $this->outcomes ?? [],
            'methods' => $this->methods ?? [],
            'grammar_topic' => $this->grammar_topic,
            'support_words' => $this->support_words ?? [],
            'scale' => $this->scale ?? [],
            'cover' => $this->cover ? "/illustrations/{$this->cover}" : null,
            'section' => new SectionResource($this->whenLoaded('section')),
            'blocks' => LessonBlockResource::collection($this->whenLoaded('blocks')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'terms' => TermResource::collection($this->whenLoaded('terms')),
            'tasks_count' => $this->whenCounted('tasks'),
            'terms_count' => $this->whenCounted('terms'),
            'progress' => $this->whenLoaded('myProgress', fn () => $this->myProgress ? [
                'status' => $this->myProgress->status,
                'done_tasks' => $this->myProgress->done_tasks,
                'total_tasks' => $this->myProgress->total_tasks,
                'score' => $this->myProgress->score,
                'max_score' => $this->myProgress->max_score,
            ] : null),
        ];
    }
}
