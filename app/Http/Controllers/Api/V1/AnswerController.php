<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnswerResource;
use App\Models\Answer;
use App\Models\Task;
use App\Services\AnswerChecker;
use App\Services\Progress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnswerController extends Controller
{
    public function __construct(
        private readonly AnswerChecker $checker,
        private readonly Progress $progress,
    ) {
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'array'],
            'status' => ['sometimes', Rule::in([Answer::DRAFT, Answer::SUBMITTED])],
        ]);

        $status = $data['status'] ?? Answer::SUBMITTED;
        $user = $request->user();

        $answer = DB::transaction(function () use ($task, $user, $data, $status) {
            $result = $status === Answer::SUBMITTED
                ? $this->checker->check($task, $data['payload'])
                : ['auto' => false, 'score' => null, 'max' => $task->maxScore(), 'details' => []];

            $answer = Answer::firstOrNew(['user_id' => $user->id, 'task_id' => $task->id]);
            $answer->payload = ['input' => $data['payload'], 'details' => $result['details']];
            $answer->status = $result['auto'] ? Answer::CHECKED : $status;
            $answer->score = $result['score'];
            $answer->max_score = $result['max'];
            $answer->submitted_at = $status === Answer::SUBMITTED ? now() : $answer->submitted_at;
            $answer->checked_at = $result['auto'] ? now() : $answer->checked_at;

            if ($status === Answer::SUBMITTED) {
                $answer->attempts = min($answer->attempts + 1, 255);
            }

            $answer->save();

            if ($task->lesson_id !== null) {
                $this->progress->recalculate($user, $task->lesson);
            }

            return $answer;
        });

        return response()->json([
            'data' => new AnswerResource($answer),
            'key' => $this->visibleKey($task, $answer),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $answers = Answer::where('user_id', $request->user()->id)
            ->with('task:id,lesson_id,number,type,prompt')
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => AnswerResource::collection($answers)]);
    }

    private function visibleKey(Task $task, Answer $answer): ?array
    {
        if ($answer->status === Answer::DRAFT || $task->answer_key === null) {
            return null;
        }

        return [
            'text' => $task->answer_key['text'] ?? null,
            'items' => $this->checker->keyItems($task),
            'answers' => $task->answer_key['answers'] ?? null,
        ];
    }
}
