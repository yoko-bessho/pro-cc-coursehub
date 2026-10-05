<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_enrollment_shows_error_toast(): void
    {
        $student = User::factory()->student()->create();
        $category = Category::factory()->create();
        $course = Course::factory()->published()->create(['category_id' => $category->id]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $response = $this->actingAs($student)
            ->from("/courses/{$course->id}")
            ->post("/courses/{$course->id}/enroll");

        $response->assertRedirect("/courses/{$course->id}");

        $followUp = $this->actingAs($student)->get("/courses/{$course->id}");

        $followUp->assertSee('既に受講登録済みです');
    }
}
