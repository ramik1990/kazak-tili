<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id', 'lesson_id', 'status', 'done_tasks', 'total_tasks',
        'score', 'max_score', 'completed_at',
    ];

    protected $casts = [
        'done_tasks' => 'integer',
        'total_tasks' => 'integer',
        'score' => 'integer',
        'max_score' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
