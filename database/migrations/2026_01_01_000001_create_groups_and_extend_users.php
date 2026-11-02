<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('speciality')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->after('password');
            $table->foreignId('group_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->string('locale', 5)->default('kk')->after('group_id');
            $table->boolean('is_active')->default(true)->after('locale');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['role', 'group_id', 'locale', 'is_active']);
        });

        Schema::dropIfExists('groups');
    }
};
