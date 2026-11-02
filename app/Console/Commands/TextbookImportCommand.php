<?php

namespace App\Console\Commands;

use App\Services\Textbook\DocxDocument;
use App\Services\Textbook\TextbookParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TextbookImportCommand extends Command
{
    protected $signature = 'textbook:import {file : Путь к .docx}
        {--json=database/seeders/data/textbook.json : Куда сохранить структуру}
        {--media=public/illustrations : Куда распаковать иллюстрации}';

    protected $description = 'Разбирает .docx пособия в структуру уроков, заданий и глоссария';

    public function handle(TextbookParser $parser): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Файл не найден: {$file}");

            return self::FAILURE;
        }

        $document = new DocxDocument($file);
        $nodes = $document->nodes();
        $this->info(sprintf('Прочитано узлов: %d', count($nodes)));

        $structure = $parser->parse($nodes);

        $media = base_path($this->option('media'));
        File::ensureDirectoryExists($media);
        $saved = $document->extractMedia($media);

        $json = base_path($this->option('json'));
        File::ensureDirectoryExists(dirname($json));
        File::put($json, json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->table(
            ['Разделов', 'Уроков', 'Заданий', 'Ключей', 'Терминов', 'Иллюстраций'],
            [[
                count($structure['sections']),
                $this->countLessons($structure),
                $this->countTasks($structure),
                $this->countKeys($structure),
                array_sum(array_map(fn ($g) => count($g['terms']), $structure['glossary'])),
                count($saved),
            ]],
        );

        $this->info("Структура: {$json}");

        return self::SUCCESS;
    }

    private function countLessons(array $structure): int
    {
        return array_sum(array_map(fn ($s) => count($s['lessons']), $structure['sections']));
    }

    private function countTasks(array $structure): int
    {
        $total = 0;

        foreach ($structure['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                $total += count($lesson['tasks']);
            }

            foreach ($section['extras'] as $extra) {
                $total += count($extra['tasks']);
            }
        }

        return $total;
    }

    private function countKeys(array $structure): int
    {
        $total = 0;

        foreach ($structure['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                $total += count(array_filter($lesson['tasks'], fn ($t) => $t['answer_key'] !== null));
            }
        }

        return $total;
    }
}
