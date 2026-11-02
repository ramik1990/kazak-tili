<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Http\Resources\SectionResource;
use App\Http\Resources\TermResource;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TextbookController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sections = Section::query()
            ->with(['lessons' => fn ($q) => $q->withCount(['tasks', 'terms'])->with('myProgress')])
            ->orderBy('number')
            ->get();

        return SectionResource::collection($sections);
    }

    public function section(Section $section): SectionResource
    {
        return new SectionResource($section->load([
            'lessons' => fn ($q) => $q->withCount(['tasks', 'terms'])->with('myProgress'),
            'tasks',
        ]));
    }

    public function lesson(Lesson $lesson): LessonResource
    {
        return new LessonResource($lesson->load([
            'section',
            'blocks',
            'tasks.myAnswer',
            'terms',
            'myProgress',
        ]));
    }

    public function glossary(Request $request): AnonymousResourceCollection
    {
        $terms = Term::query()
            ->search($request->query('search'))
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->query('lesson_id')))
            ->orderBy('lesson_id')
            ->orderBy('id')
            ->get();

        return TermResource::collection($terms);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => ['lessons' => [], 'terms' => [], 'blocks' => []]]);
        }

        $lessons = Lesson::where('title', 'like', "%{$query}%")
            ->orWhere('goal', 'like', "%{$query}%")
            ->orderBy('number')
            ->limit(10)
            ->get(['id', 'number', 'title']);

        $terms = Term::search($query)->limit(15)->get(['id', 'lesson_id', 'kk', 'ru']);

        $blocks = \App\Models\LessonBlock::where('text', 'like', "%{$query}%")
            ->with('lesson:id,number,title')
            ->limit(15)
            ->get()
            ->map(fn ($block) => [
                'lesson_id' => $block->lesson_id,
                'lesson_number' => $block->lesson?->number,
                'lesson_title' => $block->lesson?->title,
                'excerpt' => mb_substr($block->text, 0, 180),
            ]);

        return response()->json(['data' => compact('lessons', 'terms', 'blocks')]);
    }
}
