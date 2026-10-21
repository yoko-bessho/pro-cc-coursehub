<?php

namespace App\Http\Controllers;

use App\Exceptions\CourseImageUploadException;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\Tag;
use App\Services\CourseService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class CoachCourseController extends Controller
{
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

    public function store(StoreCourseRequest $request, CourseService $courseService)
    {
        try {
            $courseService->create(auth()->user(), $request->validated(), $request->file('image'));
        } catch (CourseImageUploadException $e) {
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException $e) {
            // バリデーション通過後に同一タイトルが同時に作成された場合
            return back()->withInput()->withErrors(['title' => '同じタイトルのコースが既に存在します。']);
        } catch (\Exception $e) {
            Log::error('コース作成エラー: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['image']),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors([
                'error' => 'コースの作成中にエラーが発生しました。もう一度お試しください。',
            ]);
        }

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを作成しました。');
    }

    public function edit(Course $course)
    {
        $this->authorize('update', $course);

        $categories = Category::all();
        $tags = Tag::all();
        $course->load('tags');

        return view('coach.courses.edit', compact('course', 'categories', 'tags'));
    }

    public function update(UpdateCourseRequest $request, Course $course, CourseService $courseService)
    {
        $this->authorize('update', $course);

        try {
            $courseService->update(
                $course,
                $request->validated(),
                $request->file('image'),
                $request->boolean('remove_image'),
            );
        } catch (CourseImageUploadException $e) {
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput()->withErrors(['title' => '同じタイトルのコースが既に存在します。']);
        } catch (\Exception $e) {
            Log::error('コース更新エラー: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'course_id' => $course->id,
                'request_data' => $request->except(['image']),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors([
                'error' => 'コースの更新中にエラーが発生しました。もう一度お試しください。',
            ]);
        }

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
