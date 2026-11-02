<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

class Progress
{
    public function recalculate(User $user, Lesson $lesson): LessonProgress
    {
        $tasks = $lesson->tasks()->get(['id', 'type', 'points', 'payload']);
        $answers = Answer::where('user_id', $user->id)
            ->whereIn('task_id', $tasks->pluck('id'))
            ->whereIn('status', [Answer::SUBMITTED, Answer::CHECKED])
            ->get()
            ->keyBy('task_id');

        $score = 0;
        $maxScore = 0;

        foreach ($tasks as $task) {
            $answer = $answers->get($task->id);

            if ($answer?->score === null) {
                continue;
            }

            $score += $answer->score;
            $maxScore += $answer->max_score ?? $task->maxScore();
        }

        $done = $answers->count();
        $total = $tasks->count();

        return LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'status' => $done === 0 ? 'started' : ($done >= $total ? 'completed' : 'in_progress'),
                'done_tasks' => $done,
                'total_tasks' => $total,
                'score' => $score,
                'max_score' => $maxScore,
                'completed_at' => $done >= $total && $total > 0 ? now() : null,
            ],
        );
    }

    public function mark(Lesson $lesson, array $scale, int $score, int $max): ?string
    {
        if ($scale === [] || $max === 0) {
            return null;
        }

        foreach ($scale as $row) {
            if (! preg_match('/(\d+)\s*[—–-]\s*(\d+)/u', $row['correct'] ?? '', $m)) {
                continue;
            }

            if ($score >= (int) $m[1] && $score <= (int) $m[2]) {
                return $row['mark'] ?? null;
            }
        }

        return null;
    }
}
