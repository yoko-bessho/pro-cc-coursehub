<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizRetakeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Quiz $quiz;

    private Question $question;

    private Option $correctOption;

    private Option $wrongOption;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->published()->create();
        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);
        $chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        $this->quiz = Quiz::factory()->create(['lesson_id' => $lesson->id, 'passing_score' => 70]);
        $this->question = Question::factory()->create(['quiz_id' => $this->quiz->id]);
        $this->correctOption = Option::factory()->correct()->create(['question_id' => $this->question->id]);
        $this->wrongOption = Option::factory()->create(['question_id' => $this->question->id]);
    }

    private function submitAnswer(Option $option)
    {
        return $this->actingAs($this->student)->post(
            "/courses/{$this->course->id}/quizzes/{$this->quiz->id}/submit",
            [
                'answers' => [
                    ['question_id' => $this->question->id, 'option_id' => $option->id],
                ],
            ]
        );
    }

    public function test_student_with_no_prior_submission_can_take_quiz(): void
    {
        $response = $this->submitAnswer($this->correctOption);

        $response->assertRedirect("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");
        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
        ]);
    }

    public function test_student_can_retake_quiz_after_failing(): void
    {
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 0,
        ]);

        $response = $this->submitAnswer($this->correctOption);

        $response->assertRedirect("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");
        $this->assertDatabaseCount('submissions', 2);
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
        ]);
    }

    public function test_student_cannot_submit_quiz_after_passing(): void
    {
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
        ]);

        $response = $this->submitAnswer($this->wrongOption);

        $response->assertStatus(403);
        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_student_is_redirected_to_result_when_quiz_already_passed(): void
    {
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses/{$this->course->id}/quizzes/{$this->quiz->id}");

        $response->assertRedirect("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");
    }

    public function test_student_can_still_view_quiz_form_when_not_passed(): void
    {
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 50,
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses/{$this->course->id}/quizzes/{$this->quiz->id}");

        $response->assertStatus(200);
    }

    public function test_unanswered_question_is_treated_as_incorrect(): void
    {
        $response = $this->actingAs($this->student)->post(
            "/courses/{$this->course->id}/quizzes/{$this->quiz->id}/submit",
            [
                'answers' => [
                    ['question_id' => $this->question->id],
                ],
            ]
        );

        $response->assertRedirect("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 0,
        ]);
    }

    public function test_option_from_a_different_question_is_not_counted_as_correct(): void
    {
        $otherQuestion = Question::factory()->create(['quiz_id' => $this->quiz->id]);
        $otherCorrectOption = Option::factory()->correct()->create(['question_id' => $otherQuestion->id]);

        $response = $this->actingAs($this->student)->post(
            "/courses/{$this->course->id}/quizzes/{$this->quiz->id}/submit",
            [
                'answers' => [
                    ['question_id' => $this->question->id, 'option_id' => $otherCorrectOption->id],
                ],
            ]
        );

        $response->assertRedirect("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 0,
        ]);
    }

    public function test_student_without_enrollment_cannot_submit_quiz(): void
    {
        $outsider = User::factory()->student()->create();

        $response = $this->actingAs($outsider)->post(
            "/courses/{$this->course->id}/quizzes/{$this->quiz->id}/submit",
            [
                'answers' => [
                    ['question_id' => $this->question->id, 'option_id' => $this->correctOption->id],
                ],
            ]
        );

        $response->assertStatus(403);
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_result_page_shows_submission_history_newest_first(): void
    {
        $older = Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 40,
            'submitted_at' => now()->subDays(2),
        ]);

        $newer = Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 90,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");

        $response->assertStatus(200);
        $response->assertSee('受験履歴');
        $response->assertSee('40%');
        $response->assertSee('90%');
        $response->assertSeeInOrder([
            $newer->submitted_at->format('Y/m/d H:i'),
            $older->submitted_at->format('Y/m/d H:i'),
        ]);
    }

    public function test_retake_button_is_hidden_after_passing(): void
    {
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");

        $response->assertStatus(200);
        $response->assertDontSee('再受験する');
    }

    public function test_retake_button_is_shown_after_failing(): void
    {
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 50,
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result");

        $response->assertStatus(200);
        $response->assertSee('再受験する');
    }
}
