<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class MyCourseController extends Controller
{
    public function index(Request $request)
    {
        $enrollments = Enrollment::where('user_id', auth()->id())
            ->whereIn('status', ['active', 'completed'])
            ->with('course.chapters.lessons', 'course.user')
            ->latest('enrolled_at')
            ->get();

        $progressRates = Course::batchProgressRatesForUser(
            $enrollments->pluck('course'),
            auth()->id()
        );

        return view('my-courses.index', compact('enrollments', 'progressRates'));
    }
}
