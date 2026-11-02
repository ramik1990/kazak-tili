<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonBlock extends Model
{
    protected $fillable = ['lesson_id', 'position', 'type', 'text', 'payload'];

    protected $casts = ['payload' => 'array', 'position' => 'integer'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
