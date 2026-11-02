<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\Term;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TextbookSeeder extends Seeder
{
    private const ACCENTS = ['#2E7D5B', '#1F7A8C', '#A35C12', '#4C6A24', '#6B4E9B'];

    public function run(): void
    {
        $path = database_path('seeders/data/textbook.json');

        if (! File::exists($path)) {
            $this->command?->error('Нет database/seeders/data/textbook.json — сначала php artisan textbook:import');

            return;
        }

        $data = json_decode(File::get($path), true);
        $glossary = collect($data['glossary'])->keyBy('lesson');

        $this->clear();

        DB::transaction(function () use ($data, $glossary) {

            foreach ($data['sections'] as $index => $raw) {
                $section = Section::create([
                    'number' => $raw['number'],
                    'roman' => $raw['roman'],
                    'title' => $raw['title'],
                    'lessons_range' => $raw['lessons_range'] ?: null,
                    'goal' => $raw['goal'] ?: null,
                    'objectives' => $raw['objectives'],
                    'outcomes' => $raw['outcomes'],
                    'methods' => $raw['methods'],
                    'cover' => $raw['cover'],
                    'accent' => self::ACCENTS[$index % count(self::ACCENTS)],
                ]);

                foreach ($raw['lessons'] as $rawLesson) {
                    $lesson = $this->createLesson($section, $rawLesson);
                    $this->createTerms($lesson, $glossary->get($rawLesson['number'])['terms'] ?? []);
                }

                $this->createSectionTasks($section, $raw['extras']);
            }
        });

        $this->command?->info(sprintf(
            'Разделов %d, уроков %d, блоков %d, заданий %d, терминов %d.',
            Section::count(),
            Lesson::count(),
            DB::table('lesson_blocks')->count(),
            Task::count(),
            Term::count(),
        ));
    }

    private function clear(): void
    {
        foreach (['answers', 'lesson_progress', 'terms', 'tasks', 'lesson_blocks', 'lessons', 'sections'] as $table) {
            DB::table($table)->delete();
        }
    }

    private function createLesson(Section $section, array $raw): Lesson
    {
        $lesson = $section->lessons()->create([
            'number' => $raw['number'],
            'title' => $raw['title'],
            'goal' => $raw['goal'] ?: null,
            'outcomes' => $raw['outcomes'],
            'methods' => $raw['methods'],
            'grammar_topic' => $raw['grammar_topic'] ?: null,
            'support_words' => $raw['support_words'],
            'scale' => $raw['scale'] ?? null,
            'cover' => $raw['cover'],
        ]);

        $tasks = collect($raw['tasks']);
        $position = 0;

        foreach ($raw['blocks'] as $block) {
            $position++;

            if ($block['type'] === 'task') {
                $task = $tasks->get($block['index']);

                if ($task !== null) {
                    $this->createTask($lesson->tasks(), $task, $position, ['lesson_id' => $lesson->id, 'scope' => 'lesson']);
                    $tasks->forget($block['index']);
                }

                continue;
            }

            $lesson->blocks()->create([
                'position' => $position,
                'type' => $block['type'],
                'text' => $block['text'] ?? ($block['caption'] ?? null),
                'payload' => $this->blockPayload($block),
            ]);
        }

        foreach ($tasks as $task) {
            $this->createTask($lesson->tasks(), $task, ++$position, ['lesson_id' => $lesson->id, 'scope' => 'lesson']);
        }

        return $lesson;
    }

    private function createTerms(Lesson $lesson, array $terms): void
    {
        foreach ($terms as $term) {
            if (trim($term['kk'] ?? '') === '') {
                continue;
            }

            $lesson->terms()->create([
                'kk' => $term['kk'],
                'ru' => $term['ru'] ?? '',
                'definition' => $term['definition'] ?: null,
            ]);
        }
    }

    private function blockPayload(array $block): ?array
    {
        return match ($block['type']) {
            'vocabulary' => ['words' => $block['words']],
            'table' => ['rows' => $block['rows']],
            'image' => ['files' => $block['files']],
            default => null,
        };
    }

    private function createSectionTasks(Section $section, array $extras): void
    {
        $position = 0;

        foreach ($extras as $extra) {
            foreach ($extra['tasks'] as $task) {
                $this->createTask($section->tasks(), $task, ++$position, [
                    'section_id' => $section->id,
                    'scope' => $extra['kind'] === 'test' ? 'test' : 'section',
                ]);
            }
        }
    }

    private function createTask($relation, array $raw, int $position, array $extra): void
    {
        $type = $raw['type'];
        $payload = array_filter([
            'items' => $raw['items'] ?? [],
            'options' => $raw['options'] ?? [],
            'questions' => $raw['questions'] ?? [],
        ], fn ($value) => $value !== []);

        $relation->create([
            ...$extra,
            'number' => $raw['number'],
            'position' => $position,
            'skill' => $raw['skill'],
            'type' => $type,
            'prompt' => $raw['prompt'] !== '' ? $raw['prompt'] : 'Тапсырма',
            'instruction' => $raw['instruction'] ?? null,
            'payload' => $payload ?: null,
            'answer_key' => $this->answerKey($raw),
            'points' => $type === 'test' ? max(count($raw['questions'] ?? []), 1) : 1,
            'auto_check' => $this->autoCheck($type, $raw),
        ]);
    }

    private function answerKey(array $raw): ?array
    {
        $key = [];

        if (! empty($raw['answer_key'])) {
            $key['text'] = $raw['answer_key'];
        }

        if (($raw['type'] ?? '') === 'test') {
            $answers = [];

            foreach ($raw['questions'] as $question) {
                if ($question['answer'] !== null) {
                    $answers[$question['number']] = $question['answer'];
                }
            }

            if ($answers !== []) {
                $key['answers'] = $answers;
            }
        }

        return $key ?: null;
    }

    private function autoCheck(string $type, array $raw): bool
    {
        if ($type === 'test') {
            return collect($raw['questions'] ?? [])->contains(fn ($q) => $q['answer'] !== null);
        }

        return in_array($type, ['mcq', 'true_false'], true) && ! empty($raw['answer_key']);
    }
}
