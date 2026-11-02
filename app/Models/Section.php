<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['number', 'roman', 'title', 'lessons_range', 'goal', 'objectives', 'outcomes', 'methods', 'cover', 'accent'];

    protected $casts = [
        'number' => 'integer',
        'objectives' => 'array',
        'outcomes' => 'array',
        'methods' => 'array',
    ];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('number');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }
}
