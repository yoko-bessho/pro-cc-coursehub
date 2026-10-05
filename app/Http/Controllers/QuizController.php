<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Option;
use App\Models\Quiz;
use App\Models\Submission;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function show(Course $course, Quiz $quiz)
    {
        $this->authorize('view', $course);

        $hasPassed = Submission::where('user_id', auth()->id())
            ->where('quiz_id', $quiz->id)
            ->where('score', '>=', $quiz->passing_score)
            ->exists();

        if ($hasPassed) {
            return redirect()->route('courses.quizzes.result', [$course, $quiz]);
        }

        $quiz->load('questions.options');

        return view('quizzes.show', compact('course', 'quiz'));
    }

    public function submit(Request $request, Course $course, Quiz $quiz)
    {
        $this->authorize('submit', [Submission::class, $quiz, $course]);

        $answers = $request->input('answers', []);

        $correctCount = 0;
        foreach ($quiz->questions as $question) {
            $userAnswer = collect($answers)->firstWhere('question_id', $question->id);
            $selectedOption = Option::find($userAnswer['option_id'] ?? null);
            if ($selectedOption && $selectedOption->question_id == $question->id && $selectedOption->is_correct) {
                $correctCount++;
            }
        }

        $score = $quiz->questions->isEmpty() ? 0 : (int) round($correctCount / $quiz->questions->count() * 100);

        $submission = Submission::create([
            'user_id' => auth()->id(),
            'quiz_id' => $quiz->id,
            'score' => $score,
            'answers' => $answers,
            'submitted_at' => now(),
        ]);

        return redirect()->route('courses.quizzes.result', [$course, $quiz]);
    }

    public function result(Course $course, Quiz $quiz)
    {
        $this->authorize('view', $course);

        $quiz->load('questions.options');

        $submissions = Submission::where('user_id', auth()->id())
            ->where('quiz_id', $quiz->id)
            ->orderByDesc('submitted_at')
            ->get();

        $submission = $submissions->first();

        if (! $submission) {
            abort(404);
        }

        $canRetake = auth()->user()->can('submit', [Submission::class, $quiz, $course]);

        return view('quizzes.result', compact('course', 'quiz', 'submission', 'submissions', 'canRetake'));
    }
}
