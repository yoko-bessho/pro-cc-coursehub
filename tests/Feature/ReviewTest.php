<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $student;

    private Category $category;

    private Course $course;

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
    }

    private function completeEnrollment(): void
    {
        Enrollment::factory()->completed()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);
    }

    public function test_student_who_completed_course_can_view_reviews(): void
    {
        $this->completeEnrollment();

        Review::factory()->create([
            'course_id' => $this->course->id,
            'rating' => 5,
            'comment' => 'とても良いコースでした',
        ]);

        $response = $this->actingAs($this->student)->get("/courses/{$this->course->id}");

        $response->assertStatus(200);
        $response->assertSee('レビュー');
        $response->assertSee('とても良いコースでした');
    }

    public function test_student_who_has_not_completed_course_cannot_view_reviews(): void
    {
        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        Review::factory()->create([
            'course_id' => $this->course->id,
            'comment' => '受講中には見えないはずのコメント',
        ]);

        $response = $this->actingAs($this->student)->get("/courses/{$this->course->id}");

        $response->assertStatus(200);
        $response->assertDontSee('受講中には見えないはずのコメント');
    }

    public function test_coach_can_view_reviews_but_cannot_see_form(): void
    {
        Review::factory()->create([
            'course_id' => $this->course->id,
            'comment' => 'コーチにも見えるコメント',
        ]);

        $response = $this->actingAs($this->coach)->get("/courses/{$this->course->id}");

        $response->assertStatus(200);
        $response->assertSee('コーチにも見えるコメント');
        $response->assertDontSee('レビューを投稿');
    }

    public function test_admin_can_view_reviews_but_cannot_see_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Review::factory()->create([
            'course_id' => $this->course->id,
            'comment' => '管理者にも見えるコメント',
        ]);

        $response = $this->actingAs($admin)->get("/courses/{$this->course->id}");

        $response->assertStatus(200);
        $response->assertSee('管理者にも見えるコメント');
        $response->assertDontSee('レビューを投稿');
    }

    public function test_student_who_completed_course_can_submit_review(): void
    {
        $this->completeEnrollment();

        $response = $this->actingAs($this->student)->post("/courses/{$this->course->id}/reviews", [
            'rating' => 4,
            'comment' => 'わかりやすかったです',
        ]);

        $response->assertRedirect("/courses/{$this->course->id}");
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 4,
            'comment' => 'わかりやすかったです',
        ]);
    }

    public function test_student_cannot_submit_review_twice(): void
    {
        $this->completeEnrollment();

        Review::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $response = $this->actingAs($this->student)->post("/courses/{$this->course->id}/reviews", [
            'rating' => 3,
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_student_who_has_not_completed_course_cannot_submit_review(): void
    {
        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->student)->post("/courses/{$this->course->id}/reviews", [
            'rating' => 5,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_coach_cannot_submit_review(): void
    {
        $response = $this->actingAs($this->coach)->post("/courses/{$this->course->id}/reviews", [
            'rating' => 5,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_rating_must_be_between_1_and_5(): void
    {
        $this->completeEnrollment();

        $response = $this->actingAs($this->student)->post("/courses/{$this->course->id}/reviews", [
            'rating' => 6,
        ]);

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_rating_of_zero_is_rejected(): void
    {
        $this->completeEnrollment();

        $response = $this->actingAs($this->student)->post("/courses/{$this->course->id}/reviews", [
            'rating' => 0,
        ]);

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_comment_is_optional(): void
    {
        $this->completeEnrollment();

        $response = $this->actingAs($this->student)->post("/courses/{$this->course->id}/reviews", [
            'rating' => 5,
        ]);

        $response->assertRedirect("/courses/{$this->course->id}");
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 5,
            'comment' => null,
        ]);
    }
}
