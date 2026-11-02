<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    protected $fillable = [
        'section_id', 'number', 'title', 'goal', 'outcomes', 'methods',
        'grammar_topic', 'support_words', 'scale', 'cover', 'is_published',
    ];

    protected $casts = [
        'number' => 'integer',
        'outcomes' => 'array',
        'methods' => 'array',
        'support_words' => 'array',
        'scale' => 'array',
        'is_published' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LessonBlock::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function myProgress(): HasOne
    {
        return $this->hasOne(LessonProgress::class)->where('user_id', auth()->id());
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
