<?php

namespace App\Services;

use App\Models\Task;

class AnswerChecker
{
    public function check(Task $task, array $payload): array
    {
        return match ($task->type) {
            'test' => $this->checkTest($task, $payload),
            'true_false' => $this->checkList($task, $payload, fn ($v) => $this->normalizeBool($v)),
            'fill_blank', 'mcq' => $this->checkList($task, $payload, fn ($v) => $this->normalize($v)),
            default => ['auto' => false, 'score' => null, 'max' => $task->maxScore(), 'details' => []],
        };
    }

    private function checkTest(Task $task, array $payload): array
    {
        $key = $task->answer_key['answers'] ?? [];
        $given = $payload['answers'] ?? [];

        if ($key === []) {
            return ['auto' => false, 'score' => null, 'max' => $task->maxScore(), 'details' => []];
        }

        $details = [];
        $score = 0;

        foreach ($task->questions() as $question) {
            $number = $question['number'];
            $expected = $key[$number] ?? null;
            $answer = $given[$number] ?? null;

            if ($expected === null) {
                $details[] = ['number' => $number, 'correct' => null, 'expected' => null];

                continue;
            }

            $correct = $answer !== null && $this->normalize($answer) === $this->normalize($expected);
            $score += $correct ? 1 : 0;
            $details[] = ['number' => $number, 'correct' => $correct, 'expected' => $expected];
        }

        return ['auto' => true, 'score' => $score, 'max' => count($task->questions()), 'details' => $details];
    }

    private function checkList(Task $task, array $payload, callable $normalizer): array
    {
        $key = $this->keyItems($task);
        $given = $payload['values'] ?? [];

        if ($key === []) {
            return ['auto' => false, 'score' => null, 'max' => $task->maxScore(), 'details' => []];
        }

        $details = [];
        $score = 0;

        foreach ($key as $number => $expected) {
            $answer = $given[$number] ?? null;
            $correct = $answer !== null && $normalizer($answer) === $normalizer($expected);
            $score += $correct ? 1 : 0;
            $details[] = ['number' => $number, 'correct' => $correct, 'expected' => $expected];
        }

        return ['auto' => true, 'score' => $score, 'max' => count($key), 'details' => $details];
    }

    public function keyItems(Task $task): array
    {
        $text = $task->answer_key['text'] ?? '';

        if ($text === '') {
            return [];
        }

        $items = [];

        foreach (preg_split('/\s*;\s*/u', rtrim($text, '.')) as $chunk) {
            if (preg_match('/^\s*(\d+)\s*[—–-]\s*(.+)$/u', $chunk, $m)) {
                $items[(int) $m[1]] = trim($m[2], " .\t\n");
            }
        }

        return $items;
    }

    private function normalize(string|int|bool|null $value): string
    {
        $value = is_bool($value) ? ($value ? 'дұрыс' : 'бұрыс') : (string) $value;
        $value = mb_strtolower(trim($value), 'UTF-8');

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    private function normalizeBool(string|int|bool|null $value): string
    {
        $value = $this->normalize($value);

        return match (true) {
            in_array($value, ['1', 'true', 'дұрыс', 'иә', 'да'], true) => 'true',
            in_array($value, ['0', 'false', 'бұрыс', 'жоқ', 'нет'], true) => 'false',
            default => $value,
        };
    }
}
