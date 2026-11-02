<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnswerResource;
use App\Models\Answer;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Progress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private readonly Progress $progress)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $answers = Answer::query()
            ->with(['user:id,name,group_id', 'task:id,lesson_id,number,type,skill,prompt,answer_key'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')), fn ($q) => $q->where('status', Answer::SUBMITTED))
            ->when($request->filled('group_id'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('group_id', $request->query('group_id'))))
            ->when($request->filled('lesson_id'), fn ($q) => $q->whereHas('task', fn ($t) => $t->where('lesson_id', $request->query('lesson_id'))))
            ->whereNull('score')
            ->orderBy('submitted_at')
            ->paginate(min(max((int) $request->query('per_page', 30), 1), 100))
            ->withQueryString();

        return AnswerResource::collection($answers);
    }

    public function update(Request $request, Answer $answer): AnswerResource
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [], ['score' => 'балл', 'feedback' => 'пікір']);

        $answer->fill([
            ...$data,
            'status' => Answer::CHECKED,
            'max_score' => $answer->max_score ?? $answer->task->maxScore(),
            'checked_by' => $request->user()->id,
            'checked_at' => now(),
        ])->save();

        if ($answer->task->lesson_id !== null) {
            $this->progress->recalculate($answer->user, $answer->task->lesson);
        }

        return new AnswerResource($answer->load(['user:id,name', 'task:id,lesson_id,number,prompt']));
    }

    public function groups(Request $request): JsonResponse
    {
        $groups = Group::query()
            ->with(['students:id,name,group_id,is_active'])
            ->withCount('students')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('teacher_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups]);
    }

    public function journal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['required', Rule::exists('groups', 'id')],
        ]);

        $students = User::where('group_id', $data['group_id'])
            ->where('role', User::ROLE_STUDENT)
            ->with('progress')
            ->orderBy('name')
            ->get(['id', 'name', 'group_id']);

        $lessons = Lesson::orderBy('number')->get(['id', 'number', 'title']);

        $rows = $students->map(fn (User $student) => [
            'id' => $student->id,
            'name' => $student->name,
            'lessons' => $student->progress->mapWithKeys(fn ($p) => [$p->lesson_id => [
                'status' => $p->status,
                'done_tasks' => $p->done_tasks,
                'total_tasks' => $p->total_tasks,
                'score' => $p->score,
                'max_score' => $p->max_score,
            ]]),
        ]);

        return response()->json(['data' => ['lessons' => $lessons, 'students' => $rows]]);
    }
}
