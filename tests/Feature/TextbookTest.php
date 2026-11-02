<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TextbookTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $group = Group::create(['name' => 'ЭК-101', 'is_active' => true]);
        $this->student = User::create([
            'name' => 'Студент', 'email' => 'student@test.local', 'password' => 'secret123',
            'role' => User::ROLE_STUDENT, 'group_id' => $group->id,
        ]);

        $section = Section::create(['number' => 1, 'roman' => 'I', 'title' => 'Тіл және экологиялық сана', 'goal' => 'Мақсат']);
        $this->lesson = $section->lessons()->create([
            'number' => 1,
            'title' => 'Қазақ тілі — қарым-қатынас құралы',
            'goal' => 'Сабақтың мақсаты',
            'support_words' => [['kk' => 'қарым-қатынас', 'ru' => 'общение']],
        ]);

        $this->lesson->blocks()->create(['position' => 1, 'type' => 'text', 'text' => 'Қазақ тілі — мемлекеттік тіл.']);
        $this->lesson->terms()->create(['kk' => 'Ана тілі', 'ru' => 'Родной язык', 'definition' => 'Анықтама']);
    }

    private function task(array $attributes = []): Task
    {
        return $this->lesson->tasks()->create(array_merge([
            'number' => 1,
            'position' => 2,
            'skill' => 'reading',
            'type' => 'short_text',
            'prompt' => 'Сұрақтарға жауап беріңіз.',
            'points' => 1,
        ], $attributes));
    }

    public function test_guest_reads_the_textbook_without_signing_in(): void
    {
        $this->task();

        $this->getJson('/api/v1/textbook')->assertOk()->assertJsonPath('data.0.roman', 'I');
        $this->getJson("/api/v1/sections/{$this->lesson->section_id}")->assertOk();
        $this->getJson('/api/v1/glossary')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/search?q=мемлекеттік')->assertOk();

        $this->getJson("/api/v1/lessons/{$this->lesson->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.answer', null)
            ->assertJsonPath('data.progress', null);
    }

    public function test_guest_cannot_answer_tasks(): void
    {
        $task = $this->task();

        $this->postJson("/api/v1/tasks/{$task->id}/answer", ['payload' => ['text' => 'жауап']])->assertUnauthorized();
        $this->getJson('/api/v1/answers')->assertUnauthorized();

        $this->assertDatabaseCount('answers', 0);
    }

    public function test_signed_in_student_still_sees_own_answers_on_public_endpoints(): void
    {
        $task = $this->task();
        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/tasks/{$task->id}/answer", ['payload' => ['text' => 'жауап']])->assertOk();

        $this->getJson("/api/v1/lessons/{$this->lesson->id}")
            ->assertOk()
            ->assertJsonPath('data.tasks.0.answer.status', 'submitted')
            ->assertJsonPath('data.progress.done_tasks', 1);
    }

    public function test_student_sees_sections_with_lessons(): void
    {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/textbook')
            ->assertOk()
            ->assertJsonPath('data.0.roman', 'I')
            ->assertJsonPath('data.0.lessons.0.number', 1)
            ->assertJsonPath('data.0.lessons.0.title', 'Қазақ тілі — қарым-қатынас құралы');
    }

    public function test_lesson_returns_blocks_tasks_and_terms(): void
    {
        $this->task();
        Sanctum::actingAs($this->student);

        $this->getJson("/api/v1/lessons/{$this->lesson->id}")
            ->assertOk()
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.support_words.0.kk', 'қарым-қатынас')
            ->assertJsonCount(1, 'data.blocks')
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.terms.0.ru', 'Родной язык')
            ->assertJsonPath('data.section.roman', 'I');
    }

    public function test_test_task_is_checked_automatically_against_the_key(): void
    {
        $task = $this->task([
            'type' => 'test',
            'number' => 22,
            'prompt' => 'Дұрыс жауапты таңдаңыз.',
            'points' => 2,
            'auto_check' => true,
            'payload' => ['questions' => [
                ['number' => 1, 'text' => 'Сұрақ бір', 'options' => [['label' => 'А', 'text' => 'Бір'], ['label' => 'Ә', 'text' => 'Екі']], 'answer' => 'Ә'],
                ['number' => 2, 'text' => 'Сұрақ екі', 'options' => [['label' => 'А', 'text' => 'Бір'], ['label' => 'Ә', 'text' => 'Екі']], 'answer' => 'А'],
            ]],
            'answer_key' => ['answers' => [1 => 'Ә', 2 => 'А']],
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/tasks/{$task->id}/answer", ['payload' => ['answers' => [1 => 'Ә', 2 => 'Ә']]])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked')
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.max_score', 2)
            ->assertJsonPath('data.payload.details.0.number', 1)
            ->assertJsonPath('data.payload.details.0.correct', true)
            ->assertJsonPath('data.payload.details.1.correct', false)
            ->assertJsonPath('data.payload.details.1.expected', 'А');

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'score' => 1, 'max_score' => 2,
        ]);
    }

    public function test_fill_blank_is_checked_against_the_text_key(): void
    {
        $task = $this->task([
            'type' => 'fill_blank',
            'skill' => 'writing',
            'payload' => ['items' => ['Ақпарат маңызды, ___ оны тексеру қажет.', 'Су ___ керек.']],
            'answer_key' => ['text' => '1 — себебі; 2 — үнемдеу'],
            'auto_check' => true,
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/tasks/{$task->id}/answer", ['payload' => ['values' => [1 => 'Себебі', 2 => 'сақтау']]])
            ->assertOk()
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.max_score', 2)
            ->assertJsonPath('data.payload.details.0.correct', true)
            ->assertJsonPath('data.payload.details.1.correct', false);
    }

    public function test_true_false_accepts_kazakh_words(): void
    {
        $task = $this->task([
            'type' => 'true_false',
            'payload' => ['items' => ['Су — табиғи ресурс.', 'Қоқыс пайдалы.']],
            'answer_key' => ['text' => '1 — дұрыс; 2 — бұрыс'],
            'auto_check' => true,
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/tasks/{$task->id}/answer", ['payload' => ['values' => [1 => 'дұрыс', 2 => 'бұрыс']]])
            ->assertOk()
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.status', 'checked');
    }

    public function test_open_answer_waits_for_the_teacher(): void
    {
        $task = $this->task(['skill' => 'writing', 'type' => 'essay', 'prompt' => 'Эссе жазыңыз.']);
        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/tasks/{$task->id}/answer", ['payload' => ['text' => 'Менің эссем.']])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.score', null);

        $teacher = User::create(['name' => 'Оқытушы', 'email' => 't@test.local', 'password' => 'secret123', 'role' => User::ROLE_TEACHER]);
        Sanctum::actingAs($teacher);

        $answerId = $this->getJson('/api/v1/teacher/answers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0.id');

        $this->putJson("/api/v1/teacher/answers/{$answerId}", ['score' => 5, 'feedback' => 'Жарайсың'])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked')
            ->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.feedback', 'Жарайсың');

        $this->assertDatabaseHas('lesson_progress', ['user_id' => $this->student->id, 'score' => 5]);
    }

    public function test_students_cannot_reach_teacher_and_admin_areas(): void
    {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/teacher/answers')->assertForbidden();
        $this->getJson('/api/v1/admin/tasks')->assertForbidden();
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_admin_sets_a_test_key_and_enables_auto_check(): void
    {
        $task = $this->task([
            'type' => 'test',
            'payload' => ['questions' => [['number' => 1, 'text' => 'Сұрақ', 'options' => [['label' => 'А', 'text' => 'Бір']], 'answer' => null]]],
        ]);

        $admin = User::create(['name' => 'Әкімші', 'email' => 'a@test.local', 'password' => 'secret123', 'role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/tasks/{$task->id}", ['answers' => [1 => 'А']])
            ->assertOk()
            ->assertJsonPath('data.auto_check', true)
            ->assertJsonPath('data.has_key', true);

        $this->assertSame(['answers' => [1 => 'А']], $task->fresh()->answer_key);
    }

    public function test_glossary_and_search_find_content(): void
    {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/glossary?search=Родной')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kk', 'Ана тілі');

        $this->getJson('/api/v1/search?q=мемлекеттік')
            ->assertOk()
            ->assertJsonCount(1, 'data.blocks');
    }

    public function test_registration_requires_an_active_group(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Жаңа студент',
            'email' => 'new@test.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors('group_id');

        $group = Group::first();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Жаңа студент',
            'email' => 'new@test.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_id' => $group->id,
        ])->assertCreated()->assertJsonPath('user.role', 'student');
    }
}
