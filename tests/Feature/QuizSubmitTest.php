<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizSubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->course = Course::factory()->published()->create();
        $chapter = Chapter::factory()->for($this->course)->create();
        $this->lesson = Lesson::factory()->for($chapter)->create();
    }

    public function test_score_is_100_when_all_answers_are_correct(): void
    {
        $quiz = Quiz::factory()->for($this->lesson)->create();
        $answers = [];

        foreach (range(1, 2) as $index) {
            $question = Question::factory()->for($quiz)->create();
            $correctOption = Option::factory()->for($question)->correct()->create();
            Option::factory()->for($question)->create();

            $answers[] = ['question_id' => $question->id, 'option_id' => $correctOption->id];
        }

        $response = $this->actingAs($this->student)->post(
            route('courses.quizzes.submit', [$this->course, $quiz]),
            ['answers' => $answers]
        );

        $response->assertRedirect(route('courses.quizzes.result', [$this->course, $quiz]));
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $quiz->id,
            'score' => 100,
        ]);
    }

    public function test_score_is_calculated_correctly_when_some_questions_are_unanswered(): void
    {
        $quiz = Quiz::factory()->for($this->lesson)->create();
        $answers = [];

        // 2問は正解、2問は未回答（フォームの hidden フィールドどおり question_id のみ送信）
        foreach (range(1, 2) as $index) {
            $question = Question::factory()->for($quiz)->create();
            $correctOption = Option::factory()->for($question)->correct()->create();
            Option::factory()->for($question)->create();

            $answers[] = ['question_id' => $question->id, 'option_id' => $correctOption->id];
        }

        foreach (range(1, 2) as $index) {
            $question = Question::factory()->for($quiz)->create();
            Option::factory()->for($question)->correct()->create();
            Option::factory()->for($question)->create();

            $answers[] = ['question_id' => $question->id];
        }

        $response = $this->actingAs($this->student)->post(
            route('courses.quizzes.submit', [$this->course, $quiz]),
            ['answers' => $answers]
        );

        $response->assertRedirect(route('courses.quizzes.result', [$this->course, $quiz]));
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $quiz->id,
            'score' => 50,
        ]);
    }

    public function test_score_is_0_when_all_questions_are_unanswered(): void
    {
        $quiz = Quiz::factory()->for($this->lesson)->create();
        $answers = [];

        foreach (range(1, 2) as $index) {
            $question = Question::factory()->for($quiz)->create();
            Option::factory()->for($question)->correct()->create();
            Option::factory()->for($question)->create();

            $answers[] = ['question_id' => $question->id];
        }

        $response = $this->actingAs($this->student)->post(
            route('courses.quizzes.submit', [$this->course, $quiz]),
            ['answers' => $answers]
        );

        $response->assertRedirect(route('courses.quizzes.result', [$this->course, $quiz]));
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $quiz->id,
            'score' => 0,
        ]);
    }

    public function test_score_is_0_for_quiz_with_no_questions(): void
    {
        $quiz = Quiz::factory()->for($this->lesson)->create();

        $response = $this->actingAs($this->student)->post(
            route('courses.quizzes.submit', [$this->course, $quiz]),
            ['answers' => []]
        );

        $response->assertRedirect(route('courses.quizzes.result', [$this->course, $quiz]));
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $quiz->id,
            'score' => 0,
        ]);
    }
}
