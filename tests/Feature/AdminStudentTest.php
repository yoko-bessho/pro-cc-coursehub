<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStudentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_view_student_list_with_enrollment_count(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::factory()->create();

        $courses = Course::factory()->count(3)->create(['category_id' => $category->id]);
        foreach ($courses as $course) {
            Enrollment::factory()->create([
                'user_id' => $student->id,
                'course_id' => $course->id,
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/admin/students');

        $response->assertStatus(200);
        $response->assertSee($student->name);
        $response->assertSee($student->email);
        $response->assertSee('<td class="px-6 py-4 text-sm text-gray-500">3</td>', false);
    }

    public function test_admin_student_list_shows_zero_when_student_has_no_enrollments(): void
    {
        User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($this->admin)->get('/admin/students');

        $response->assertStatus(200);
        $response->assertSee('<td class="px-6 py-4 text-sm text-gray-500">0</td>', false);
    }

    public function test_non_admin_cannot_view_student_list(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get('/admin/students');

        $response->assertStatus(403);
    }
}
