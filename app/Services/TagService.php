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
            // 第3引数をnullにしてascii変換をスキップし、日本語などの非ASCII文字も保持する。
            // 変換しない場合、Str::slug()はASCIIへの変換時に日本語を除去して空文字を返すため、
            // 異なるタグ名が同じ空slugに一致し誤って同一タグとして扱われてしまう。
            $slug = Str::slug($tagName, '-', null);

            if ($slug === '') {
                $slug = 'tag-'.Str::random(8);
            }

            $tag = Tag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $tagName]
            );

            return $tag->id;
        }, $tagNames);
    }
}
