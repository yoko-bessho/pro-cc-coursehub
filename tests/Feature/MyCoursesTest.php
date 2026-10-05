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

class MyCoursesTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $coach;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->category = Category::factory()->create();
    }

    public function test_student_can_view_progress_rate_for_enrolled_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $chapter = Chapter::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['chapter_id' => $chapter->id]);

        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $course->id,
        ]);

        LessonProgress::factory()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $lessons[0]->id,
        ]);

        $response = $this->actingAs($this->student)->get('/my-courses');

        $response->assertStatus(200);
        $response->assertSee($course->title);
        $response->assertSee('50%');
    }

    public function test_student_sees_zero_percent_progress_when_not_started(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $chapter = Chapter::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['chapter_id' => $chapter->id]);

        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $course->id,
        ]);

        $response = $this->actingAs($this->student)->get('/my-courses');

        $response->assertStatus(200);
        $response->assertSee('0%');
    }

    public function test_student_progress_excludes_unpublished_lessons_from_total(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $chapter = Chapter::factory()->create(['course_id' => $course->id]);
        $publishedLesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        Lesson::factory()->unpublished()->create(['chapter_id' => $chapter->id]);

        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $course->id,
        ]);

        LessonProgress::factory()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $publishedLesson->id,
        ]);

        $response = $this->actingAs($this->student)->get('/my-courses');

        $response->assertStatus(200);
        $response->assertSee('100%');
    }

    public function test_student_sees_correct_progress_for_each_of_multiple_enrolled_courses(): void
    {
        $courseA = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $chapterA = Chapter::factory()->create(['course_id' => $courseA->id]);
        $lessonsA = Lesson::factory()->count(2)->create(['chapter_id' => $chapterA->id]);

        $courseB = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $chapterB = Chapter::factory()->create(['course_id' => $courseB->id]);
        $lessonsB = Lesson::factory()->count(4)->create(['chapter_id' => $chapterB->id]);

        Enrollment::factory()->create(['user_id' => $this->student->id, 'course_id' => $courseA->id]);
        Enrollment::factory()->create(['user_id' => $this->student->id, 'course_id' => $courseB->id]);

        LessonProgress::factory()->create(['user_id' => $this->student->id, 'lesson_id' => $lessonsA[0]->id]);
        LessonProgress::factory()->create(['user_id' => $this->student->id, 'lesson_id' => $lessonsB[0]->id]);

        $response = $this->actingAs($this->student)->get('/my-courses');

        $response->assertStatus(200);
        $response->assertSee('50%');
        $response->assertSee('25%');
    }
}
