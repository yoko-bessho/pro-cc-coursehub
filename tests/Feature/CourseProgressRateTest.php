<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseProgressRateTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private User $student;
    private Category $category;
    private Course $course;
    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->category = Category::factory()->create();

        $this->course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $this->chapter = Chapter::factory()->create([
            'course_id' => $this->course->id,
        ]);
    }

    public function test_progress_rate_is_100_percent_when_all_published_lessons_are_completed_even_with_unpublished_lessons(): void
    {
        $publishedLessons = Lesson::factory()->count(3)->create([
            'chapter_id' => $this->chapter->id,
            'is_published' => true,
        ]);

        Lesson::factory()->count(2)->unpublished()->create([
            'chapter_id' => $this->chapter->id,
        ]);

        foreach ($publishedLessons as $lesson) {
            LessonProgress::factory()->create([
                'user_id' => $this->student->id,
                'lesson_id' => $lesson->id,
                'status' => 'completed',
            ]);
        }

        $progressRate = $this->course->getProgressRate($this->student->id);

        $this->assertSame(100, $progressRate);
    }

    public function test_progress_rate_reflects_partial_completion_of_published_lessons(): void
    {
        $publishedLessons = Lesson::factory()->count(4)->create([
            'chapter_id' => $this->chapter->id,
            'is_published' => true,
        ]);

        Lesson::factory()->unpublished()->create([
            'chapter_id' => $this->chapter->id,
        ]);

        LessonProgress::factory()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $publishedLessons[0]->id,
            'status' => 'completed',
        ]);

        LessonProgress::factory()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $publishedLessons[1]->id,
            'status' => 'completed',
        ]);

        LessonProgress::factory()->notStarted()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $publishedLessons[2]->id,
        ]);

        $progressRate = $this->course->getProgressRate($this->student->id);

        $this->assertSame(50, $progressRate);
    }

    public function test_progress_rate_is_0_percent_when_course_has_no_lessons(): void
    {
        $progressRate = $this->course->getProgressRate($this->student->id);

        $this->assertSame(0, $progressRate);
    }
}
