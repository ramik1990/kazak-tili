<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Әкімші', 'password' => 'password', 'role' => User::ROLE_ADMIN, 'locale' => 'ru'],
        );

        $teacher = User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            ['name' => 'Мухтанова А. К.', 'password' => 'password', 'role' => User::ROLE_TEACHER, 'locale' => 'kk'],
        );

        $group = Group::firstOrCreate(
            ['name' => 'ЭК-101'],
            ['speciality' => 'Экология және табиғатты қорғау қызметі', 'year' => 1, 'teacher_id' => $teacher->id],
        );

        User::firstOrCreate(
            ['email' => 'student@example.com'],
            ['name' => 'Студент', 'password' => 'password', 'role' => User::ROLE_STUDENT, 'group_id' => $group->id, 'locale' => 'ru'],
        );

        $this->call(TextbookSeeder::class);
    }
}
