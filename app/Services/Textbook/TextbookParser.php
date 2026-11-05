<?php

namespace App\Services\Textbook;

class TextbookParser
{
    private const SKILLS = [
        'reading' => "\u{1F4D6}",
        'listening' => "\u{1F3A7}",
        'speaking' => "\u{1F5E3}",
        'writing' => "\u{270D}",
        'literature' => "\u{1F4DA}",
        'project' => "\u{1F33F}",
    ];

    private const META = [
        'goal' => ['Сабақтың мақсаты', 'Оқу мақсаты'],
        'outcomes' => ['Күтілетін нәтиже', 'Күтілетін оқу нәтижелері'],
        'methods' => ['Әдістер'],
        'grammar_topic' => ['Грамматикалық тақырып'],
        'support_words' => ['Тірек сөздер'],
    ];

    private array $nodes = [];

    private array $sections = [];

    private array $glossary = [];

    private array $references = [];

    private array $about = [];

    private array $contents = [];

    private ?array $test = null;

    private ?array $section = null;

    private ?array $lesson = null;

    private ?array $task = null;

    private ?string $keyFor = null;

    private int $lessonCounter = 0;

    public function parse(array $nodes): array
    {
        $this->nodes = $nodes;
        $bounds = $this->bounds();

        $this->parseAbout($bounds['body']);
        $this->parseBody($bounds['body'], $bounds['glossary']);
        $this->parseGlossary($bounds['glossary'], $bounds['references']);
        $this->parseReferences($bounds['references']);

        $this->closeLesson();
        $this->closeSection();

        return [
            'about' => $this->about,
            'sections' => $this->sections,
            'glossary' => $this->glossary,
            'references' => $this->references,
        ];
    }

    private function bounds(): array
    {
        $body = null;
        $glossary = count($this->nodes);
        $references = count($this->nodes);

        foreach ($this->nodes as $i => $node) {
            if ($node['type'] !== 'paragraph') {
                continue;
            }

            if ($body === null && $this->isSection($node['text'])) {
                $body = $i;
            }

            if ($glossary === count($this->nodes) && preg_match('/^ГЛОССАРИЙ/u', $node['text'])) {
                $glossary = $i;
            }

            if ($references === count($this->nodes) && preg_match('/ПАЙДАЛАНЫЛҒАН ӘДЕБИЕТТЕР/u', $node['text'])) {
                $references = $i;
            }
        }

        return ['body' => $body ?? 0, 'glossary' => $glossary, 'references' => $references];
    }

    private function parseAbout(int $end): void
    {
        $lines = [];
        $skills = [];
        $methods = [];

        for ($i = 0; $i < $end; $i++) {
            $node = $this->nodes[$i];

            if ($node['type'] === 'table' && $methods === [] && count($node['rows'][0] ?? []) === 3) {
                $methods = $this->rowsToMaps($node['rows'], ['method', 'origin', 'usage']);

                continue;
            }

            if ($node['type'] !== 'paragraph') {
                continue;
            }

            if ($this->skillOf($node['text']) !== null && preg_match('/^\p{So}\x{FE0F}?\s*([^\-–—]+?)\s*(?:[-–—]\s*(.+))?$/u', $node['text'], $m)) {
                $skills[] = [
                    'code' => $this->skillOf($node['text']),
                    'icon' => $this->firstEmoji($node['text']),
                    'name' => trim($m[1]),
                    'description' => rtrim(trim($m[2] ?? ''), '.'),
                ];

                continue;
            }

            if (count($lines) < 8) {
                $lines[] = $node['text'];
            }
        }

        $this->contents = $this->parseContents($end);

        $this->about = [
            'author' => $lines[0] ?? '',
            'title' => $lines[1] ?? '',
            'subject' => $lines[2] ?? '',
            'kind' => $lines[3] ?? '',
            'audience' => $lines[4] ?? '',
            'skills' => array_values(array_filter($skills, fn ($s) => $s['code'] !== null)),
            'methods' => $methods,
        ];
    }

    private function parseContents(int $end): array
    {
        $entries = [];
        $covers = [];

        for ($i = 0; $i < $end; $i++) {
            $node = $this->nodes[$i];

            if ($node['type'] !== 'paragraph') {
                continue;
            }

            foreach ($node['images'] as $image) {
                $covers[] = $image;
            }

            if (preg_match('/^\s*(I|II|III|IV|V)\s+бөлім\s*\./u', $node['text'])) {
                $entries[] = ['goal' => '', 'lessons' => ''];

                continue;
            }

            if ($entries === []) {
                continue;
            }

            $last = count($entries) - 1;

            if (preg_match('/^\s*(\d+\s*[–-]\s*\d+)\s*[-–]сабақтар/u', $node['text'], $m)) {
                $entries[$last]['lessons'] = $m[1];
            }

            if (preg_match('/^Мақсаты\s*[:：]\s*(.+)$/u', $node['text'], $m)) {
                $entries[$last]['goal'] = rtrim($m[1], '.');
            }
        }

        foreach ($entries as $index => $entry) {
            $entries[$index]['cover'] = $covers[$index] ?? null;
        }

        return $entries;
    }

    private function parseBody(int $start, int $end): void
    {
        for ($i = $start; $i < $end; $i++) {
            $node = $this->nodes[$i];

            if ($node['type'] === 'table') {
                $this->pushTable($node['rows']);

                continue;
            }

            $text = $node['text'];

            if ($this->isSection($text)) {
                $this->startSection($text);

                continue;
            }

            if ($this->isSectionExtra($text)) {
                $this->startExtra($text);

                continue;
            }

            if (preg_match('/^\s*(\d+)\s*[-–—]\s*САБАҚ\s*[\.\s]/ui', $text, $m)) {
                $this->startLesson($text);

                continue;
            }

            if (preg_match('/^жауап кілті/ui', $text)) {
                $this->closeTest();
                $this->keyFor = 'open';
                $this->task = null;

                continue;
            }

            if ($this->keyFor !== null) {
                if ($this->parseAnswerKey($text)) {
                    continue;
                }
            }

            if ($this->section === null) {
                continue;
            }

            if ($this->lesson !== null && $this->captureMeta($text)) {
                continue;
            }

            if (preg_match('/қорытынды тест|^тест$/ui', $text)) {
                $this->startTest($text);

                continue;
            }

            if ($this->test !== null
                && $this->test['questions'] === []
                && preg_match('/^(.{0,10}?)(\d+)\s*[-–—]\s*тапсырма\s*[\.\:]?\s*(.*)$/ui', $text, $m)) {
                $this->test['number'] = (int) $m[2];
                $this->test['skill'] = $this->skillOf($text) ?? 'reading';
                $this->test['prompt'] = trim($m[3]) ?: $this->test['prompt'];

                continue;
            }

            if ($this->test !== null && $this->captureTestLine($text)) {
                continue;
            }

            if (preg_match('/^(.{0,10}?)(\d+)\s*[-–—]\s*тапсырма\s*[\.\:]?/ui', $text, $m)) {
                $this->closeTest();
                $this->startTask((int) $m[2], $text);

                continue;
            }

            if ($node['images'] !== []) {
                $this->pushBlock(['type' => 'image', 'files' => $node['images'], 'caption' => $text]);

                continue;
            }

            $this->pushParagraph($text, $node['style']);
        }
    }

    private function isSection(string $text): bool
    {
        return (bool) preg_match('/^\s*(I|II|III|IV|V)\s+БӨЛІМ\s*\./u', $text) && ! str_contains($text, 'БОЙЫНША');
    }

    private function isSectionExtra(string $text): bool
    {
        return (bool) preg_match('/БӨЛІМ БОЙЫНША|^\s*\p{So}*\s*БӨЛІМ БОЙЫНША/u', $text)
            || (bool) preg_match('/^[IVX]+ бөлім бойынша/u', $text);
    }

    private function startSection(string $text): void
    {
        $this->closeTest();
        $this->closeLesson();
        $this->closeSection();

        preg_match('/^\s*(I|II|III|IV|V)\s+БӨЛІМ\s*\.\s*(.+)$/u', $text, $m);
        $number = count($this->sections) + 1;
        $contents = $this->contents[$number - 1] ?? [];

        $this->section = [
            'number' => $number,
            'roman' => $m[1] ?? '',
            'title' => $this->sentenceCase($m[2] ?? $text),
            'lessons_range' => $contents['lessons'] ?? '',
            'goal' => $contents['goal'] ?? '',
            'objectives' => [],
            'outcomes' => [],
            'methods' => [],
            'answer_keys' => [],
            'cover' => $contents['cover'] ?? null,
            'lessons' => [],
            'extras' => [],
        ];
        $this->lesson = null;
        $this->task = null;
        $this->keyFor = null;
    }

    private function startExtra(string $text): void
    {
        $this->closeTest();
        $this->closeLesson();

        if ($this->section === null) {
            return;
        }

        $kind = $this->extraKind($text);

        $this->section['extras'][] = [
            'title' => $this->sentenceCase(preg_replace('/^\s*\p{So}*\s*/u', '', $text)),
            'kind' => $kind,
            'blocks' => [],
            'tasks' => [],
        ];
        $this->task = null;
        $this->keyFor = null;

        if ($kind === 'test') {
            $this->startTest($text);
        }
    }

    private function extraKind(string $text): string
    {
        return match (true) {
            str_contains($text, 'ТЕСТ') || str_contains($text, 'тест') => 'test',
            str_contains($text, 'ӨЗІН-ӨЗІ') || str_contains($text, 'өзін-өзі') => 'self_assessment',
            str_contains($text, 'ЖОБА') || str_contains($text, 'жоба') => 'project',
            default => 'review',
        };
    }

    private function startLesson(string $text): void
    {
        $this->closeTest();
        $this->closeLesson();

        if ($this->section === null) {
            return;
        }

        [, $title] = $this->splitHeading($text);
        $this->lessonCounter++;

        $this->lesson = [
            'number' => $this->lessonCounter,
            'title' => $this->sentenceCase($title),
            'goal' => '',
            'outcomes' => [],
            'methods' => [],
            'grammar_topic' => '',
            'support_words' => [],
            'cover' => null,
            'blocks' => [],
            'tasks' => [],
            'answer_keys' => [],
        ];
        $this->task = null;
        $this->keyFor = null;
    }

    private function captureMeta(string $text): bool
    {
        foreach (self::META as $field => $labels) {
            foreach ($labels as $label) {
                if (! preg_match('/^'.preg_quote($label, '/').'\s*[:：]\s*(.*)$/u', $text, $m)) {
                    continue;
                }

                $value = trim($m[1]);

                if ($field === 'support_words') {
                    $this->lesson['support_words'] = $this->parseWordPairs($value);
                } elseif ($field === 'methods') {
                    $this->lesson['methods'] = array_values(array_filter(array_map('trim', explode(',', rtrim($value, '.')))));
                } elseif ($field === 'outcomes') {
                    $this->lesson['outcomes'] = $value === '' ? [] : [$value];
                    $this->lesson['_collect'] = 'outcomes';
                    $this->task = null;

                    return true;
                } elseif ($this->lesson[$field] === '') {
                    $this->lesson[$field] = rtrim($value, '.');
                }

                $this->task = null;

                return true;
            }
        }

        return false;
    }

    private function parseWordPairs(string $value): array
    {
        $pairs = [];

        foreach (preg_split('/\s*;\s*/u', rtrim($value, '.')) as $chunk) {
            $parts = preg_split('/\s+[—–]\s+|\s+-\s+/u', $chunk, 2);

            if (count($parts) === 2 && trim($parts[0]) !== '') {
                $pairs[] = ['kk' => trim($parts[0]), 'ru' => trim($parts[1])];
            }
        }

        return $pairs;
    }

    private function startTask(int $number, string $text): void
    {
        $prompt = trim(preg_replace('/^(.{0,10}?)(\d+)\s*[-–—]\s*тапсырма\s*[\.\:]?\s*/ui', '', $text));

        $this->task = [
            'number' => $number,
            'skill' => $this->skillOf($text) ?? 'reading',
            'type' => 'short_text',
            'prompt' => $prompt,
            'items' => [],
            'options' => [],
            'answer_key' => null,
        ];

        $this->currentTasks($this->task);
    }

    private function startTest(string $text): void
    {
        $this->closeTest();
        $this->task = null;

        $this->test = [
            'number' => 0,
            'skill' => 'reading',
            'type' => 'test',
            'prompt' => $this->sentenceCase($text),
            'instruction' => '',
            'questions' => [],
            'items' => [],
            'options' => [],
            'answer_key' => null,
        ];
    }

    private function captureTestLine(string $text): bool
    {
        if (preg_match('/^(Нұсқаулық|Нускаулык)\s*[:：]\s*(.+)$/u', $text, $m)) {
            $this->test['instruction'] = trim($m[2]);

            return true;
        }

        if (preg_match('/^жауап кілті|өзін-өзі|бағалау шкаласы/ui', $text)) {
            $this->closeTest();

            return false;
        }

        $options = $this->splitOptions($text);

        if ($options !== [] && $this->test['questions'] !== []) {
            $last = count($this->test['questions']) - 1;
            $this->test['questions'][$last]['options'] = array_merge($this->test['questions'][$last]['options'], $options);

            return true;
        }

        if (preg_match('/^(\d+)\.\s*(.+)$/u', $text, $m)) {
            $this->test['questions'][] = ['number' => (int) $m[1], 'text' => trim($m[2]), 'options' => [], 'answer' => null];

            return true;
        }

        if (preg_match('/^(I{1,3}|IV|V)\.\s*(.+)$/u', $text, $m)) {
            return true;
        }

        return $this->test['questions'] !== [];
    }

    private function closeTest(): void
    {
        if ($this->test === null) {
            return;
        }

        $test = $this->test;
        $this->test = null;

        if ($test['questions'] === []) {
            return;
        }

        $this->currentTasks($test);
    }

    private function skillOf(string $text): ?string
    {
        foreach (self::SKILLS as $code => $icon) {
            if (str_contains($text, $icon)) {
                return $code;
            }
        }

        return null;
    }

    private function firstEmoji(string $text): string
    {
        preg_match('/\p{So}[\x{FE0F}]?/u', $text, $m);

        return $m[0] ?? '';
    }

    private function pushParagraph(string $text, string $style): void
    {
        if ($this->task !== null) {
            if ($this->isHeading($text, $style)) {
                $this->task = null;
            } else {
                $this->appendTaskLine($text);

                return;
            }
        }

        if (($this->lesson['_collect'] ?? null) === 'outcomes') {
            if ($this->isOutcomeLine($text)) {
                $this->lesson['outcomes'][] = $text;

                return;
            }

            unset($this->lesson['_collect']);
        }

        $this->pushBlock([
            'type' => $this->isHeading($text, $style) ? 'heading' : 'text',
            'text' => $text,
        ]);
    }

    private function isOutcomeLine(string $text): bool
    {
        return (bool) preg_match('/[;\.]$/u', $text)
            && mb_strlen($text) < 160
            && ! preg_match('/^[«"]/u', $text)
            && count($this->lesson['outcomes']) < 12;
    }

    private function isHeading(string $text, string $style): bool
    {
        if (preg_match('/^Жаңа сөздер$|^\d+\.\s*Жаңа сөздер$/u', $text)) {
            return true;
        }

        if (in_array($style, ['1', '2', '3', 'a6'], true)) {
            return true;
        }

        if (mb_strlen($text) > 90 || str_ends_with($text, '.')) {
            return false;
        }

        return (bool) preg_match('/^(\d+\.\s*)?[А-ЯЁҚҒҢӨҰҮҺІӘA-Z«"]/u', $text)
            && (bool) preg_match('/^(Грамматика|Жаңа сөздер|Мәтінмен жұмыс|Үй тапсырмасы|Өзін-өзі бағалау|Практикалық|Жауап кілті|Қалыптастырушы|Тест|Мәтін|Сөздік|Диалог|Жаттығу|Қорытынды)/ui', $text);
    }

    private function appendTaskLine(string $text): void
    {
        $option = $this->splitOptions($text);

        if ($option !== []) {
            $this->currentTaskField('options', array_merge($this->task['options'], $option));

            return;
        }

        $this->currentTaskField('items', array_merge($this->task['items'], [$text]));
    }

    private function splitOptions(string $text): array
    {
        if (! preg_match('/^\s*([ABCDА-Я])\)\s*/u', $text)) {
            return [];
        }

        $parts = preg_split('/(?:^|\s)([ABCDАӘБВГ])\)\s*/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $options = [];

        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $options[] = ['label' => $parts[$i], 'text' => trim($parts[$i + 1])];
        }

        return $options;
    }

    private function pushTable(array $rows): void
    {
        $header = implode(' ', $rows[0] ?? []);

        if (str_contains($header, 'Сұрақ') && str_contains($header, 'Жауап')) {
            $this->storeTestAnswers($rows);

            return;
        }

        if (str_contains($header, 'Дұрыс жауап саны')) {
            $this->storeScale($rows);

            return;
        }

        if ($this->task !== null) {
            $this->currentTaskField('items', array_merge($this->task['items'], [['table' => $rows]]));

            return;
        }

        $previous = $this->lastBlock();
        $isVocabulary = count($rows[0] ?? []) === 2
            && $previous !== null
            && preg_match('/Жаңа сөздер|Сөздік|Тірек сөздер/ui', $previous['text'] ?? '');

        if ($isVocabulary) {
            if ($this->lesson !== null && preg_match('/^(\d+\.\s*)?Жаңа сөздер$/u', $previous['text'] ?? '')) {
                array_pop($this->lesson['blocks']);
            }

            $this->pushBlock(['type' => 'vocabulary', 'words' => $this->rowsToMaps($rows, ['kk', 'ru'], true)]);

            return;
        }

        $this->pushBlock(['type' => 'table', 'rows' => $rows]);
    }

    private function storeTestAnswers(array $rows): void
    {
        $answers = [];

        foreach (array_slice($rows, 1) as $row) {
            for ($i = 0; $i + 1 < count($row); $i += 2) {
                $number = trim($row[$i]);
                $answer = trim($row[$i + 1]);

                if (is_numeric($number) && $answer !== '') {
                    $answers[(int) $number] = $answer;
                }
            }
        }

        if ($answers === []) {
            return;
        }

        if ($this->lesson !== null) {
            $this->lesson['test_answers'] = ($this->lesson['test_answers'] ?? []) + $answers;

            return;
        }

        if ($this->section !== null) {
            $this->section['test_answers'] = ($this->section['test_answers'] ?? []) + $answers;
        }
    }

    private function storeScale(array $rows): void
    {
        $scale = $this->rowsToMaps($rows, ['correct', 'mark']);

        if ($this->lesson !== null) {
            $this->lesson['scale'] = $scale;

            return;
        }

        if ($this->section !== null) {
            $this->section['scale'] = $scale;
        }
    }

    private function rowsToMaps(array $rows, array $keys, bool $skipHeader = true): array
    {
        $out = [];
        $first = true;

        foreach ($rows as $row) {
            if ($first && $skipHeader) {
                $first = false;

                continue;
            }

            $first = false;
            $map = [];

            foreach ($keys as $index => $key) {
                $map[$key] = trim($row[$index] ?? '');
            }

            if (implode('', $map) !== '') {
                $out[] = $map;
            }
        }

        return $out;
    }

    private function parseAnswerKey(string $text): bool
    {
        if (preg_match('/^(\d+)\s*[-–—]\s*тапсырма[^:]*:\s*(.*)$/ui', $text, $m)) {
            $this->keyFor = $m[1];
            $this->storeKey((int) $m[1], trim($m[2]));

            return true;
        }

        if (is_numeric($this->keyFor)) {
            $this->storeKey((int) $this->keyFor, $text);

            return true;
        }

        return false;
    }

    private function storeKey(int $number, string $value): void
    {
        if ($value === '') {
            return;
        }

        if ($this->lesson !== null) {
            $this->lesson['answer_keys'][$number] = trim(($this->lesson['answer_keys'][$number] ?? '').' '.$value);

            return;
        }

        if ($this->section !== null) {
            $this->section['answer_keys'][$number] = trim(($this->section['answer_keys'][$number] ?? '').' '.$value);
        }
    }

    private function currentTasks(array $task): void
    {
        if ($this->lesson !== null) {
            $this->lesson['tasks'][] = $task;
            $this->lesson['blocks'][] = ['type' => 'task', 'index' => count($this->lesson['tasks']) - 1];

            return;
        }

        if ($this->section !== null && $this->section['extras'] !== []) {
            $this->section['extras'][count($this->section['extras']) - 1]['tasks'][] = $task;
        }
    }

    private function currentTaskField(string $field, array $value): void
    {
        $this->task[$field] = $value;

        if ($this->lesson !== null && $this->lesson['tasks'] !== []) {
            $this->lesson['tasks'][count($this->lesson['tasks']) - 1][$field] = $value;

            return;
        }

        if ($this->section !== null && $this->section['extras'] !== []) {
            $last = count($this->section['extras']) - 1;
            $tasks = $this->section['extras'][$last]['tasks'];

            if ($tasks !== []) {
                $this->section['extras'][$last]['tasks'][count($tasks) - 1][$field] = $value;
            }
        }
    }

    private function pushBlock(array $block): void
    {
        if ($this->lesson !== null) {
            $this->lesson['blocks'][] = $block;

            if ($block['type'] === 'image' && $this->lesson['cover'] === null) {
                $this->lesson['cover'] = $block['files'][0];
            }

            return;
        }

        if ($this->section === null) {
            return;
        }

        if ($this->section['extras'] !== []) {
            $this->section['extras'][count($this->section['extras']) - 1]['blocks'][] = $block;

            return;
        }

        $this->captureSectionIntro($block);
    }

    private function captureSectionIntro(array $block): void
    {
        $text = $block['text'] ?? '';

        if ($block['type'] === 'image') {
            $this->section['cover'] ??= $block['files'][0];

            return;
        }

        if ($block['type'] === 'table') {
            $this->section['methods'] = $this->rowsToMaps($block['rows'], ['method', 'usage']);

            return;
        }

        if (preg_match('/^Бөлімнің мақсаты\s*[:：]\s*(.+)$/u', $text, $m)) {
            $this->section['goal'] = rtrim($m[1], '.');
            $this->section['_list'] = null;

            return;
        }

        if (preg_match('/^(Бөлімнің міндеттері|Күтілетін оқу нәтижелері)/u', $text)) {
            $this->section['_list'] = str_starts_with($text, 'Бөлімнің міндеттері') ? 'objectives' : 'outcomes';

            return;
        }

        $list = $this->section['_list'] ?? null;

        if ($list !== null && mb_strlen($text) < 200) {
            $this->section[$list][] = $text;
        }
    }

    private function lastBlock(): ?array
    {
        $blocks = $this->lesson['blocks'] ?? ($this->section['extras'] !== [] ? end($this->section['extras'])['blocks'] : []);

        return $blocks === [] ? null : end($blocks);
    }

    private function closeLesson(): void
    {
        if ($this->lesson === null || $this->section === null) {
            return;
        }

        $answers = $this->lesson['test_answers'] ?? [];
        unset($this->lesson['_collect']);

        $this->lesson['tasks'] = array_map(
            fn ($t) => $this->applyTestAnswers($this->finishTask($t, $this->lesson['answer_keys']), $answers),
            $this->lesson['tasks'],
        );
        unset($this->lesson['answer_keys'], $this->lesson['test_answers']);

        $this->section['lessons'][] = $this->lesson;
        $this->lesson = null;
        $this->task = null;
    }

    private function closeSection(): void
    {
        if ($this->section === null) {
            return;
        }

        unset($this->section['_list']);
        $keys = $this->section['answer_keys'];
        $answers = $this->section['test_answers'] ?? [];

        $this->section['extras'] = array_map(function ($extra) use ($keys, $answers) {
            $extra['tasks'] = array_map(
                fn ($t) => $this->applyTestAnswers($this->finishTask($t, $keys), $answers),
                $extra['tasks'],
            );

            return $extra;
        }, $this->section['extras']);

        unset($this->section['answer_keys'], $this->section['test_answers']);

        $this->sections[] = $this->section;
        $this->section = null;
    }

    private function finishTask(array $task, array $keys): array
    {
        $task['answer_key'] = $keys[$task['number']] ?? null;
        $task['type'] = $this->taskType($task);
        $task['items'] = array_values(array_filter($task['items'], fn ($i) => is_array($i) || trim((string) $i) !== ''));

        return $task;
    }

    private function applyTestAnswers(array $task, array $answers): array
    {
        if (($task['type'] ?? '') !== 'test' || $answers === []) {
            return $task;
        }

        $task['questions'] = array_map(function ($question) use ($answers) {
            $question['answer'] = $answers[$question['number']] ?? null;

            return $question;
        }, $task['questions']);

        return $task;
    }

    private function taskType(array $task): string
    {
        if (($task['type'] ?? '') === 'test') {
            return 'test';
        }

        $text = $task['prompt'].' '.implode(' ', array_map(fn ($i) => is_array($i) ? '' : $i, $task['items']));

        return match (true) {
            count($task['options']) >= 2 => 'mcq',
            (bool) preg_match('/дұрыс.{0,20}бұрыс|бұрыс.{0,20}дұрыс/ui', $text) => 'true_false',
            (bool) preg_match('/сәйкестендір/ui', $task['prompt']) => 'matching',
            (bool) preg_match('/топқа бөл|екі топқа|топтастыр/ui', $task['prompt']) => 'sorting',
            str_contains($text, '___') => 'fill_blank',
            (bool) preg_match('/эссе|шығарма жаз/ui', $task['prompt']) => 'essay',
            in_array($task['skill'], ['speaking', 'project'], true) => 'offline',
            default => 'short_text',
        };
    }

    private function parseGlossary(int $start, int $end): void
    {
        $lesson = null;

        for ($i = $start; $i < $end; $i++) {
            $node = $this->nodes[$i];

            if ($node['type'] === 'table' && $lesson !== null) {
                $terms = $this->rowsToMaps($node['rows'], ['no', 'kk', 'ru', 'definition']);
                $this->glossary[] = [
                    'lesson' => $lesson['number'],
                    'title' => $lesson['title'],
                    'terms' => array_map(fn ($t) => ['kk' => $t['kk'], 'ru' => $t['ru'], 'definition' => $t['definition']], $terms),
                ];
                $lesson = null;

                continue;
            }

            if ($node['type'] !== 'paragraph') {
                continue;
            }

            if (preg_match('/^\s*(\d+)\s*[-–—]\s*сабақ\s*\.\s*(.*)$/ui', $node['text'], $m)) {
                $lesson = ['number' => (int) $m[1], 'title' => trim($m[2])];
            }
        }
    }

    private function parseReferences(int $start): void
    {
        $group = null;

        for ($i = $start; $i < count($this->nodes); $i++) {
            $node = $this->nodes[$i];

            if ($node['type'] !== 'paragraph') {
                continue;
            }

            if (preg_match('/^\s*(\d+)\.\s*([А-ЯЁҚҒҢӨҰҮҺІӘA-Z].{5,})$/u', $node['text'], $m) && ! str_contains($node['text'], '–')) {
                $group = ['title' => trim($m[2]), 'items' => []];
                $this->references[] = &$group;
                unset($group);
                $group = &$this->references[count($this->references) - 1];

                continue;
            }

            if ($group !== null && mb_strlen($node['text']) > 20) {
                $group['items'][] = $node['text'];
            }
        }
    }

    private function splitHeading(string $text): array
    {
        $parts = preg_split('/\s*\.\s*/u', $text, 2);

        return [trim($parts[0] ?? ''), trim($parts[1] ?? $text)];
    }

    private function sentenceCase(string $text): string
    {
        if (mb_strtoupper($text, 'UTF-8') !== $text) {
            return $text;
        }

        $lower = mb_strtolower($text, 'UTF-8');

        $result = mb_strtoupper(mb_substr($lower, 0, 1), 'UTF-8').mb_substr($lower, 1);

        return preg_replace_callback('/\b(i{1,3}|iv|v)\b/ui', fn ($m) => mb_strtoupper($m[1], 'UTF-8'), $result);
    }
}
