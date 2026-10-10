<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * コースのタグ付けに関するロジックを集約するサービスクラス
 *
 * CoachCourseController の store/update から切り出した。
 * カンマ区切りの新規タグ名から Tag レコードを作成し、既存タグIDと統合してコースに同期する。
 */
class TagService
{
    public function syncCourseTags(Course $course, array $tagIds, ?string $newTagsInput): void
    {
        $newTagIds = $this->createTagsFromInput($newTagsInput);

        $course->tags()->sync(array_unique(array_merge($tagIds, $newTagIds)));
    }

    /**
     * カンマ区切りの新規タグ名から Tag レコードを取得・作成し、IDの配列を返す
     *
     * @return array<int>
     */
    private function createTagsFromInput(?string $newTagsInput): array
    {
        if (empty($newTagsInput)) {
            return [];
        }

        $tagNames = array_unique(array_filter(array_map('trim', explode(',', $newTagsInput))));

        return array_map(function (string $tagName) {
            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );

            return $tag->id;
        }, $tagNames);
    }
}
