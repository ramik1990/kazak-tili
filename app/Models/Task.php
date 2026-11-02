<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    public const AUTO_TYPES = ['test', 'mcq', 'true_false', 'fill_blank', 'matching', 'sorting'];

    public const SKILLS = ['reading', 'listening', 'speaking', 'writing', 'literature', 'project'];

    protected $fillable = [
        'lesson_id', 'section_id', 'scope', 'number', 'position', 'skill', 'type',
        'prompt', 'instruction', 'payload', 'answer_key', 'audio_path', 'points', 'auto_check',
    ];

    protected $casts = [
        'number' => 'integer',
        'position' => 'integer',
        'points' => 'integer',
        'payload' => 'array',
        'answer_key' => 'array',
        'auto_check' => 'boolean',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function myAnswer(): HasOne
    {
        return $this->hasOne(Answer::class)->where('user_id', auth()->id());
    }

    public function questions(): array
    {
        return $this->payload['questions'] ?? [];
    }

    public function maxScore(): int
    {
        return $this->type === 'test' ? max(count($this->questions()), 1) : $this->points;
    }

    public function needsReview(): bool
    {
        return ! $this->auto_check && in_array($this->type, ['essay', 'short_text', 'fill_blank'], true);
    }
}
