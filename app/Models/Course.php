<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'description',
        'difficulty',
        'image_path',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'course_tag');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function getAllLessonIds(): array
    {
        return Lesson::whereIn('chapter_id', $this->chapters()->pluck('id'))
            ->where('is_published', true)
            ->pluck('id')
            ->toArray();
    }

    /**
     * このコースについて、複数ユーザー分の進捗率を一括集計する（1クエリで完了数を集計）。
     *
     * @param  array<int>  $userIds
     * @return array<int, int> user_id => progress rate (%)
     */
    public function getProgressRatesForUsers(array $userIds): array
    {
        $lessonIds = $this->getAllLessonIds();
        $totalLessons = count($lessonIds);

        $completedCounts = [];
        if (! empty($userIds) && ! empty($lessonIds)) {
            $completedCounts = LessonProgress::whereIn('user_id', $userIds)
                ->whereIn('lesson_id', $lessonIds)
                ->where('status', 'completed')
                ->selectRaw('user_id, count(*) as completed_count')
                ->groupBy('user_id')
                ->pluck('completed_count', 'user_id')
                ->toArray();
        }

        $rates = [];
        foreach ($userIds as $userId) {
            $completed = $completedCounts[$userId] ?? 0;
            $rates[$userId] = $totalLessons > 0 ? (int) round($completed / $totalLessons * 100) : 0;
        }

        return $rates;
    }

    /**
     * 複数コースについて、1ユーザー分の進捗率を一括集計する（1クエリで完了レッスンを集計）。
     * $courses は chapters.lessons を eager load 済みであることを前提とする。
     *
     * @param  iterable<Course>  $courses
     * @return array<int, int> course_id => progress rate (%)
     */
    public static function batchProgressRatesForUser(iterable $courses, int $userId): array
    {
        $totalLessonsByCourse = [];
        $publishedLessonIdToCourseId = [];

        foreach ($courses as $course) {
            $total = 0;
            foreach ($course->chapters as $chapter) {
                foreach ($chapter->lessons as $lesson) {
                    if ($lesson->is_published) {
                        $total++;
                        $publishedLessonIdToCourseId[$lesson->id] = $course->id;
                    }
                }
            }
            $totalLessonsByCourse[$course->id] = $total;
        }

        $completedCountByCourse = [];
        if (! empty($publishedLessonIdToCourseId)) {
            $completedLessonIds = LessonProgress::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereIn('lesson_id', array_keys($publishedLessonIdToCourseId))
                ->pluck('lesson_id');

            foreach ($completedLessonIds as $lessonId) {
                $courseId = $publishedLessonIdToCourseId[$lessonId];
                $completedCountByCourse[$courseId] = ($completedCountByCourse[$courseId] ?? 0) + 1;
            }
        }

        $rates = [];
        foreach ($courses as $course) {
            $total = $totalLessonsByCourse[$course->id];
            $completed = $completedCountByCourse[$course->id] ?? 0;
            $rates[$course->id] = $total > 0 ? (int) round($completed / $total * 100) : 0;
        }

        return $rates;
    }
}
