<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCourseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $coach;

    private User $student;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->category = Category::factory()->create();
    }

    // =========================================================================
    // コース一覧表示（管理者）
    // =========================================================================

    // --- 正常系 ---

    public function test_admin_can_view_courses_of_any_status(): void
    {
        Course::factory()->published()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '公開コース',
        ]);
        Course::factory()->draft()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '下書きコース',
        ]);
        Course::factory()->archived()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => 'アーカイブコース',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/courses');

        $response->assertStatus(200);
        $response->assertSee('公開コース');
        $response->assertSee('下書きコース');
        $response->assertSee('アーカイブコース');
    }

    public function test_admin_course_list_can_be_filtered_by_status(): void
    {
        Course::factory()->published()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '公開コース',
        ]);
        Course::factory()->draft()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '下書きコース',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/courses?status=draft');

        $response->assertStatus(200);
        $response->assertSee('下書きコース');
        $response->assertDontSee('公開コース');
    }

    // --- 異常系 ---

    public function test_non_admin_cannot_view_admin_course_list(): void
    {
        $response = $this->actingAs($this->coach)->get('/admin/courses');

        $response->assertStatus(403);
    }

    public function test_student_cannot_view_admin_course_list(): void
    {
        $response = $this->actingAs($this->student)->get('/admin/courses');

        $response->assertStatus(403);
    }

    public function test_student_cannot_view_admin_dashboard(): void
    {
        $response = $this->actingAs($this->student)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_view_admin_course_list(): void
    {
        $response = $this->get('/admin/courses');

        $response->assertRedirect('/login');
    }

    // =========================================================================
    // コース削除（管理者）
    // =========================================================================

    // --- 正常系 ---

    // NOTE: CoursePolicy::delete() は「コース作成者本人のみ許可」（$user->id === $course->user_id）
    // という実装になっているが、AdminCourseController::destroy() は $this->authorize() を呼ばず
    // $course->delete() を直接実行しており、Policyの権限定義と矛盾した実装になっている。
    // 「管理者は他コーチのコースも削除できる」が意図した仕様なのか、
    // 本来は authorize() を呼ぶべきでPolicy側に管理者許可を足し忘れているだけなのか要確認。
    // 仕様が確定するまで、正常系の網羅はコメントアウトで保留する。
    //
    // public function test_admin_can_delete_other_coachs_course(): void
    // {
    //     $course = Course::factory()->create([
    //         'user_id' => $this->coach->id,
    //         'category_id' => $this->category->id,
    //     ]);
    //
    //     $response = $this->actingAs($this->admin)->delete("/admin/courses/{$course->id}");
    //
    //     $response->assertRedirect('/admin/courses');
    //     $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    // }

    // --- 異常系 ---

    public function test_non_admin_cannot_delete_course_via_admin_route(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->delete("/admin/courses/{$course->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_student_cannot_delete_course_via_admin_route(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->student)->delete("/admin/courses/{$course->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }
}
