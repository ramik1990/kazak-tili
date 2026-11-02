<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function tasks(Request $request): AnonymousResourceCollection
    {
        $tasks = Task::query()
            ->with('lesson:id,number,title')
            ->when($request->filled('skill'), fn ($q) => $q->where('skill', $request->query('skill')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->query('lesson_id')))
            ->when($request->boolean('without_audio'), fn ($q) => $q->whereNull('audio_path'))
            ->when($request->boolean('without_key'), fn ($q) => $q->whereNull('answer_key'))
            ->orderBy('lesson_id')
            ->orderBy('position')
            ->paginate(min(max((int) $request->query('per_page', 50), 1), 200))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function uploadAudio(Request $request, Task $task): TaskResource
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/ogg,audio/wav,audio/webm', 'max:30720'],
        ], [], ['audio' => 'аудиофайл']);

        if ($task->audio_path) {
            Storage::disk('public')->delete($task->audio_path);
        }

        $task->audio_path = $request->file('audio')->store('audio', 'public');
        $task->save();

        return new TaskResource($task);
    }

    public function deleteAudio(Task $task): JsonResponse
    {
        if ($task->audio_path) {
            Storage::disk('public')->delete($task->audio_path);
            $task->update(['audio_path' => null]);
        }

        return response()->json(['message' => 'Аудио жойылды.']);
    }

    public function updateTask(Request $request, Task $task): TaskResource
    {
        $data = $request->validate([
            'prompt' => ['sometimes', 'required', 'string', 'max:2000'],
            'instruction' => ['nullable', 'string', 'max:2000'],
            'skill' => ['sometimes', Rule::in(Task::SKILLS)],
            'points' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:50'],
            'key_text' => ['nullable', 'string', 'max:2000'],
        ], [], ['prompt' => 'тапсырма мәтіні', 'answers' => 'жауаптар']);

        $key = $task->answer_key ?? [];

        if (array_key_exists('answers', $data)) {
            $answers = array_filter($data['answers'] ?? [], fn ($v) => $v !== null && $v !== '');
            $key['answers'] = $answers;
        }

        if (array_key_exists('key_text', $data)) {
            $key['text'] = $data['key_text'];
        }

        $task->fill(array_intersect_key($data, array_flip(['prompt', 'instruction', 'skill', 'points'])));
        $task->answer_key = array_filter($key, fn ($v) => $v !== null && $v !== [] && $v !== '') ?: null;
        $task->auto_check = in_array($task->type, Task::AUTO_TYPES, true) && $task->answer_key !== null;
        $task->save();

        return new TaskResource($task);
    }
}
