<?php

namespace App\Services;

use App\Exceptions\CourseImageUploadException;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * コースの作成・更新に関するロジックを集約するサービスクラス
 *
 * CoachCourseController の store/update から切り出した。
 * Course・タグ・初期Chapter の変更を1トランザクションにまとめ、失敗時は保存済みの画像も取り除く。
 */
class CourseService
{
    private const SLUG_RETRY_LIMIT = 3;

    public function __construct(private TagService $tagService) {}

    /**
     * コースを作成し、タグ同期と初期Chapter作成までを1トランザクションで行う
     *
     * @throws CourseImageUploadException 画像の保存に失敗した場合
     * @throws UniqueConstraintViolationException title の一意制約に違反した場合（競合時）
     */
    public function create(User $coach, array $validated, ?UploadedFile $image = null): Course
    {
        $imagePath = $image ? $this->storeCourseImage($image) : null;

        try {
            return $this->createWithSlugRetry($coach, $validated, $imagePath);
        } catch (\Throwable $e) {
            $this->deleteCourseImage($imagePath);

            throw $e;
        }
    }

    /**
     * コースを更新し、タグ同期までを1トランザクションで行う
     *
     * 旧画像は DB 更新が確定した後に削除する（先に消すと更新失敗時に不整合が残るため）。
     *
     * @throws CourseImageUploadException 画像の保存に失敗した場合
     * @throws UniqueConstraintViolationException title の一意制約に違反した場合（競合時）
     */
    public function update(Course $course, array $validated, ?UploadedFile $image = null, bool $removeImage = false): Course
    {
        $oldImagePath = $course->image_path;
        $imagePath = $oldImagePath;
        $newImagePath = null;

        if ($image) {
            $newImagePath = $this->storeCourseImage($image);
            $imagePath = $newImagePath;
        } elseif ($removeImage) {
            $imagePath = null;
        }

        try {
            DB::transaction(function () use ($course, $validated, $imagePath) {
                $course->update([
                    'title' => $validated['title'],
                    'category_id' => $validated['category_id'],
                    'description' => $validated['description'],
                    'difficulty' => $validated['difficulty'],
                    'status' => $validated['status'],
                    'image_path' => $imagePath,
                    'published_at' => $validated['status'] === 'published' && ! $course->published_at ? now() : $course->published_at,
                ]);

                $this->tagService->syncCourseTags($course, $validated['tags'] ?? [], $validated['new_tags'] ?? null);
            });
        } catch (\Throwable $e) {
            $this->deleteCourseImage($newImagePath);

            throw $e;
        }

        if ($imagePath !== $oldImagePath) {
            $this->deleteCourseImage($oldImagePath);
        }

        return $course;
    }

    /**
     * slug の一意制約違反（同時リクエストとの競合）時は slug を再生成して作り直す
     */
    private function createWithSlugRetry(User $coach, array $validated, ?string $imagePath): Course
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(function () use ($coach, $validated, $imagePath) {
                    $course = Course::create([
                        'user_id' => $coach->id,
                        'category_id' => $validated['category_id'],
                        'title' => $validated['title'],
                        'slug' => $this->generateUniqueSlug($validated['title']),
                        'description' => $validated['description'],
                        'difficulty' => $validated['difficulty'],
                        'image_path' => $imagePath,
                        'status' => $validated['status'],
                        'published_at' => $validated['status'] === 'published' ? now() : null,
                    ]);

                    $this->tagService->syncCourseTags($course, $validated['tags'] ?? [], $validated['new_tags'] ?? null);

                    // コース作成直後にレッスンを追加できるよう、最初の Chapter を自動生成する
                    Chapter::create([
                        'course_id' => $course->id,
                        'title' => 'はじめに',
                        'order' => 1,
                    ]);

                    return $course;
                });
            } catch (UniqueConstraintViolationException $e) {
                if (! $this->isSlugViolation($e) || $attempt >= self::SLUG_RETRY_LIMIT) {
                    throw $e;
                }
            }
        }
    }

    /**
     * タイトルからスラッグを生成し、既存コースと重複する場合は連番を付与して一意にする
     */
    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);

        if (empty($slug)) {
            $slug = 'course-'.time();
        }

        $originalSlug = $slug;
        $slugCount = 1;
        while (Course::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$slugCount;
            $slugCount++;
        }

        return $slug;
    }

    private function isSlugViolation(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), 'courses_slug_unique')
            || str_contains($e->getMessage(), 'courses.slug');
    }

    /**
     * アップロードされた画像をユニークなファイル名で courses ディスクに保存する
     *
     * @throws CourseImageUploadException
     */
    private function storeCourseImage(UploadedFile $image): string
    {
        // getClientOriginalExtension()はクライアント提供のファイル名由来で偽装可能なため、
        // 実際のMIMEタイプから拡張子を導出するextension()を使う(画像/PHPポリグロットによる拡張子偽装対策)
        $fileName = time().'_'.Str::random(10).'.'.$image->extension();

        $path = $image->storeAs('courses', $fileName, 'public');

        if (! $path) {
            throw new CourseImageUploadException('画像のアップロードに失敗しました。');
        }

        return $path;
    }

    /**
     * 指定されたパスの画像ファイルが存在する場合、ストレージから削除する
     */
    private function deleteCourseImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
