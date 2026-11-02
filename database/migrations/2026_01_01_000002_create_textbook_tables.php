<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('roman', 5);
            $table->string('title');
            $table->string('lessons_range', 20)->nullable();
            $table->text('goal')->nullable();
            $table->json('objectives')->nullable();
            $table->json('outcomes')->nullable();
            $table->json('methods')->nullable();
            $table->string('cover')->nullable();
            $table->string('accent', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('title');
            $table->text('goal')->nullable();
            $table->json('outcomes')->nullable();
            $table->json('methods')->nullable();
            $table->string('grammar_topic')->nullable();
            $table->json('support_words')->nullable();
            $table->json('scale')->nullable();
            $table->string('cover')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['section_id', 'number']);
        });

        Schema::create('lesson_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 20);
            $table->text('text')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 20)->default('lesson');
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('position');
            $table->string('skill', 20);
            $table->string('type', 20);
            $table->text('prompt');
            $table->text('instruction')->nullable();
            $table->json('payload')->nullable();
            $table->json('answer_key')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->boolean('auto_check')->default(false);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
            $table->index(['section_id', 'position']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('kk');
            $table->string('ru');
            $table->text('definition')->nullable();
            $table->timestamps();

            $table->index('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('lesson_blocks');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('sections');
    }
};
