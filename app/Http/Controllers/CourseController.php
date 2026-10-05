<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\Review;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    // TODO: バリデーションを追加（search パラメータのサニタイズ）
    public function index(Request $request)
    {
        // Get published courses for the listing page
        $query = Course::where('status', 'published')
            ->with(['category', 'user'])
            ->withCount(['chapters', 'enrollments']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->input('difficulty'));
        }

        $courses = $query->latest()->paginate(12);
        $categories = Category::all();

        return view('courses.index', compact('courses', 'categories'));
    }

    // Display course detail page with chapters and lessons
    public function show(Course $course)
    {
        $this->authorize('view', $course);

        // コース関連データを一括取得
        $course->load('chapters.lessons', 'user', 'category', 'tags');

        $user = auth()->user();

        $enrollment = null;
        if ($user->isStudent()) {
            $enrollment = $course->enrollments()
                ->where('user_id', $user->id)
                ->first();
        }

        $canViewReviews = $user->can('viewAny', [Review::class, $course]);
        $canReview = $user->can('create', [Review::class, $course]);
        $reviews = $canViewReviews
            ? $course->reviews()->with('user')->latest()->get()
            : collect();

        return view('courses.show', compact('course', 'enrollment', 'reviews', 'canViewReviews', 'canReview'));
    }
}
