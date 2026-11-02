<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const CHECKED = 'checked';

    protected $fillable = [
        'user_id', 'task_id', 'payload', 'status', 'score', 'max_score',
        'attempts', 'feedback', 'checked_by', 'checked_at', 'submitted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'score' => 'integer',
        'max_score' => 'integer',
        'attempts' => 'integer',
        'checked_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
