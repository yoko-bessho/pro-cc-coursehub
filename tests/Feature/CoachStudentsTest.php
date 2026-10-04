<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachStudentsTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->category = Category::factory()->create();
    }

    public function test_coach_can_view_student_progress_rate(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $chapter = Chapter::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['chapter_id' => $chapter->id]);

        $student = User::factory()->create(['role' => 'student']);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lessons[0]->id,
        ]);

        $response = $this->actingAs($this->coach)->get("/coach/courses/{$course->id}/students");

        $response->assertStatus(200);
        $response->assertSee($student->name);
        $response->assertSee($student->email);
        $response->assertSee('50%');
    }

    public function test_coach_sees_zero_percent_progress_when_student_has_not_started(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $chapter = Chapter::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['chapter_id' => $chapter->id]);

        $student = User::factory()->create(['role' => 'student']);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $response = $this->actingAs($this->coach)->get("/coach/courses/{$course->id}/students");

        $response->assertStatus(200);
        $response->assertSee('0%');
    }
}
