<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = $this->payload ?? [];

        if (isset($payload['files'])) {
            $payload['files'] = array_map(fn ($file) => "/illustrations/{$file}", $payload['files']);
        }

        return [
            'id' => $this->id,
            'position' => $this->position,
            'type' => $this->type,
            'text' => $this->text,
            'payload' => $payload ?: null,
        ];
    }
}
