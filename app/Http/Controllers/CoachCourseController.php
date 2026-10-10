<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CoachCourseController extends Controller
{
    public function __construct(private TagService $tagService) {}

    public function index()
    {
        $courses = Course::where('user_id', auth()->id())
            ->withCount('chapters', 'enrollments')
            ->latest()
            ->get();

        return view('coach.courses.index', compact('courses'));
    }

    public function dashboard()
    {
        $user = auth()->user();
        $courses = Course::where('user_id', $user->id)->get();
        $totalStudents = 0;
        foreach ($courses as $course) {
            $totalStudents += $course->enrollments()->where('status', 'active')->count();
        }

        return view('coach.dashboard', [
            'courseCount' => $courses->count(),
            'publishedCount' => $courses->where('status', 'published')->count(),
            'totalStudents' => $totalStudents,
        ]);
    }

    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();

        return view('coach.courses.create', compact('categories', 'tags'));
    }

    /**
     * コース新規作成処理
     *
     * バリデーションは StoreCourseRequest に委譲し、スラッグ生成・画像アップロードは
     * private メソッド、タグ同期は TagService に分割した上で、Course レコード作成から
     * 初期Chapter作成・公開日時設定までを本メソッドで orchestrate する。
     */
    public function store(StoreCourseRequest $request)
    {
        try {

            $validated = $request->validated();

            $slug = $this->generateUniqueSlug($validated['title']);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $this->storeCourseImage($request->file('image'));

                if (! $imagePath) {
                    return back()->withInput()->withErrors([
                        'image' => '画像のアップロードに失敗しました。',
                    ]);
                }
            }

            // ============================================
            // 1. Course レコード作成
            // ============================================

            // コースをデータベースに保存
            $course = Course::create([
                'user_id' => auth()->id(),
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'],
                'difficulty' => $validated['difficulty'],
                'image_path' => $imagePath,
                'status' => $validated['status'],
                'published_at' => null, // 後で設定する
            ]);

            $this->tagService->syncCourseTags($course, $validated['tags'] ?? [], $validated['new_tags'] ?? null);

            // ============================================
            // 2. 初期 Chapter の自動作成
            // ============================================

            // コース作成時に最初のチャプターを自動生成
            // これにより、コーチがすぐにレッスンを追加できる
            Chapter::create([
                'course_id' => $course->id,
                'title' => 'はじめに',
                'order' => 1,
            ]);

            // ============================================
            // 3. ステータスに応じた published_at の設定
            // ============================================

            // 公開ステータスの場合は公開日時を設定
            if ($validated['status'] === 'published') {
                $course->update([
                    'published_at' => now(),
                ]);
            }

            // 下書きの場合は published_at は null のまま
            // ※ archived は新規作成時には選択不可

            // ============================================
            // 4. リダイレクト
            // ============================================

            // コース一覧にリダイレクト（成功メッセージ付き）
            return redirect()->route('coach.courses.index')
                ->with('success', 'コースを作成しました。');

        } catch (\Exception $e) {

            // ============================================
            // 5. エラーハンドリング
            // ============================================

            // ログにエラーを記録
            \Log::error('コース作成エラー: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['image']),
                'trace' => $e->getTraceAsString(),
            ]);

            // エラーメッセージを表示して入力フォームに戻す
            return back()->withInput()->withErrors([
                'error' => 'コースの作成中にエラーが発生しました。もう一度お試しください。',
            ]);
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

    /**
     * アップロードされた画像をユニークなファイル名で courses ディスクに保存する
     */
    private function storeCourseImage(UploadedFile $image): string|false
    {
        $fileName = time().'_'.Str::random(10).'.'.$image->getClientOriginalExtension();

        return $image->storeAs('courses', $fileName, 'public');
    }

    public function edit(Course $course)
    {
        $this->authorize('update', $course);

        $categories = Category::all();
        $tags = Tag::all();
        $course->load('tags');

        return view('coach.courses.edit', compact('course', 'categories', 'tags'));
    }

    public function update(UpdateCourseRequest $request, Course $course)
    {
        $this->authorize('update', $course);

        $validated = $request->validated();

        $course->update([
            'title' => $validated['title'],
            'category_id' => $validated['category_id'],
            'description' => $validated['description'],
            'difficulty' => $validated['difficulty'],
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' && ! $course->published_at ? now() : $course->published_at,
        ]);

        $this->tagService->syncCourseTags($course, $validated['tags'] ?? [], $validated['new_tags'] ?? null);

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを更新しました。');
    }

    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);

        $course->delete();

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを削除しました。');
    }
}
