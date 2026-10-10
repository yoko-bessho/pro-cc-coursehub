<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $student;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->category = Category::factory()->create();
    }

    public function test_student_can_view_course_list(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->student)->get('/courses');

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_course_list_displays_category_title_description_coach_chapter_count_and_enrollment_count(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'Laravel入門講座',
            'description' => 'Laravelの基礎から実践までを学ぶコースです。',
        ]);

        Chapter::factory()->count(3)->create(['course_id' => $course->id]);
        Enrollment::factory()->count(2)->create(['course_id' => $course->id]);

        $response = $this->actingAs($this->student)->get('/courses');

        $response->assertStatus(200);
        $response->assertSee($this->category->name);
        $response->assertSee('Laravel入門講座');
        $response->assertSee('Laravelの基礎から実践までを学ぶコースです。');
        $response->assertSee($this->coach->name);
        $response->assertSee('3 チャプター');
        $response->assertSee('2名受講中');
    }

    public function test_course_list_displays_zero_counts_when_course_has_no_chapters_or_enrollments(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'チャプターなしコース',
        ]);

        $response = $this->actingAs($this->student)->get('/courses');

        $response->assertStatus(200);
        $response->assertSee('0 チャプター');
        $response->assertSee('0名受講中');
    }

    public function test_student_can_view_published_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->student)->get("/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_coach_can_create_course(): void
    {
        $tags = Tag::factory()->count(2)->create();

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'テストコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'tags' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'title' => 'テストコース',
            'user_id' => $this->coach->id,
        ]);
    }

    public function test_coach_cannot_create_course_with_duplicate_title(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '重複コース',
        ]);

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '重複コース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors([
            'title' => '同じタイトルのコースが既に存在します。',
        ]);
        $this->assertDatabaseCount('courses', 1);
    }

    public function test_course_slug_is_generated_from_title(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'Laravel Basics',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', 'Laravel Basics')->first();
        $this->assertEquals('laravel-basics', $course->slug);
    }

    public function test_course_slug_falls_back_to_timestamp_for_japanese_title(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '日本語タイトルのみ',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', '日本語タイトルのみ')->first();
        $this->assertStringStartsWith('course-', $course->slug);
    }

    public function test_course_slug_is_made_unique_on_collision(): void
    {
        $firstResponse = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'Hello World!',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);
        $firstResponse->assertRedirect('/coach/courses');

        $secondResponse = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'Hello World?',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);
        $secondResponse->assertRedirect('/coach/courses');

        $firstCourse = Course::where('title', 'Hello World!')->first();
        $secondCourse = Course::where('title', 'Hello World?')->first();

        $this->assertEquals('hello-world', $firstCourse->slug);
        $this->assertEquals('hello-world-1', $secondCourse->slug);
    }

    public function test_coach_can_create_course_with_image(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('course.jpg');

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '画像付きコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'image' => $image,
        ]);

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', '画像付きコース')->first();
        $this->assertNotNull($course->image_path);
        Storage::disk('public')->assertExists($course->image_path);
    }

    public function test_published_at_is_set_when_status_is_published(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '公開コース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'published',
        ]);

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', '公開コース')->first();
        $this->assertNotNull($course->published_at);
    }

    public function test_coach_can_sync_existing_tags_when_creating_course(): void
    {
        $tags = Tag::factory()->count(2)->create();

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'タグ付きコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'tags' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', 'タグ付きコース')->first();
        $this->assertEqualsCanonicalizing(
            $tags->pluck('id')->toArray(),
            $course->tags()->pluck('tags.id')->toArray()
        );
    }

    public function test_coach_can_create_new_tags_when_creating_course(): void
    {
        $existingTag = Tag::factory()->create();

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '新規タグ付きコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'tags' => [$existingTag->id],
            'new_tags' => 'Laravel, PHP',
        ]);

        $response->assertRedirect('/coach/courses');

        $this->assertDatabaseHas('tags', ['name' => 'Laravel']);
        $this->assertDatabaseHas('tags', ['name' => 'PHP']);

        $course = Course::where('title', '新規タグ付きコース')->first();
        $this->assertEqualsCanonicalizing(
            [$existingTag->name, 'Laravel', 'PHP'],
            $course->tags()->pluck('name')->toArray()
        );
    }

    public function test_coach_cannot_create_course_with_new_tags_longer_than_255_characters(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '長すぎる新規タグのコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'new_tags' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('new_tags');
        $this->assertDatabaseMissing('courses', ['title' => '長すぎる新規タグのコース']);
    }

    public function test_initial_chapter_is_created_when_creating_course(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '初期チャプターコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', '初期チャプターコース')->first();
        $this->assertDatabaseHas('chapters', [
            'course_id' => $course->id,
            'title' => 'はじめに',
            'order' => 1,
        ]);
    }

    public function test_coach_can_update_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '更新されたタイトル',
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'published',
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => '更新されたタイトル',
        ]);
    }

    public function test_coach_cannot_update_course_with_title_already_used_by_another_own_course(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '既存コース',
        ]);
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '既存コース',
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'published',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_coach_can_update_course_while_keeping_its_own_title(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '変わらないタイトル',
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '変わらないタイトル',
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'archived',
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => '変わらないタイトル',
            'status' => 'archived',
        ]);
    }

    public function test_coach_can_sync_tags_and_create_new_tags_when_updating_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
        $existingTag = Tag::factory()->create();

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => $course->title,
            'category_id' => $this->category->id,
            'description' => $course->description,
            'difficulty' => $course->difficulty,
            'status' => $course->status,
            'tags' => [$existingTag->id],
            'new_tags' => 'Vue.js',
        ]);

        $response->assertRedirect('/coach/courses');

        $this->assertDatabaseHas('tags', ['name' => 'Vue.js']);
        $this->assertEqualsCanonicalizing(
            [$existingTag->name, 'Vue.js'],
            $course->tags()->pluck('name')->toArray()
        );
    }

    public function test_coach_cannot_update_course_with_new_tags_longer_than_255_characters(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => $course->title,
            'category_id' => $this->category->id,
            'description' => $course->description,
            'difficulty' => $course->difficulty,
            'status' => $course->status,
            'new_tags' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('new_tags');
    }

    public function test_coach_cannot_update_other_coachs_course(): void
    {
        $otherCoach = User::factory()->create(['role' => 'coach']);
        $course = Course::factory()->create([
            'user_id' => $otherCoach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '乗っ取りタイトル',
            'category_id' => $this->category->id,
            'description' => '乗っ取り説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'published',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courses', [
            'id' => $course->id,
            'title' => '乗っ取りタイトル',
        ]);
    }

    public function test_coach_can_delete_own_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->delete("/coach/courses/{$course->id}");

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_coach_cannot_delete_other_coaches_course(): void
    {
        $otherCoach = User::factory()->create(['role' => 'coach']);
        $course = Course::factory()->create([
            'user_id' => $otherCoach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->delete("/coach/courses/{$course->id}");

        $response->assertStatus(403);
    }

    public function test_student_cannot_create_course(): void
    {
        $response = $this->actingAs($this->student)->get('/coach/courses/create');

        $response->assertStatus(403);
    }

    public function test_course_list_can_be_filtered_by_category(): void
    {
        $otherCategory = Category::factory()->create();

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'カテゴリAのコース',
        ]);

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $otherCategory->id,
            'status' => 'published',
            'title' => 'カテゴリBのコース',
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses?category={$this->category->id}");

        $response->assertStatus(200);
        $response->assertSee('カテゴリAのコース');
        $response->assertDontSee('カテゴリBのコース');
    }

    public function test_course_list_can_be_searched(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'Laravel入門',
        ]);

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'React基礎',
        ]);

        $response = $this->actingAs($this->student)
            ->get('/courses?search=Laravel');

        $response->assertStatus(200);
        $response->assertSee('Laravel入門');
        $response->assertDontSee('React基礎');
    }
}
