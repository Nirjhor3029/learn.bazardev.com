<?php

namespace App\Http\Controllers;

use App\Models\MockExamAttempt;
use App\Services\MockExam;
use App\Services\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A full mock sitting: Listening (self-reported, real test taken on British Council's
 * site), Reading (auto-graded, original content), Writing and Speaking (saved for a
 * side-by-side comparison with Band 6 / Band 8 samples, never auto-scored). See
 * App\Services\MockExam for the band-conversion and content-loading logic.
 */
class MockExamController extends Controller
{
    public function __construct(private MockExam $mockExam, private Quiz $quiz) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $attempts = $user->mockExamAttempts()->latest()->get();

        return view('mock-exams.index', [
            'sets' => $this->mockExam->sets(),
            'attempts' => $attempts,
        ]);
    }

    public function start(Request $request, string $set): RedirectResponse
    {
        abort_unless($this->mockExam->exists($set), 404);

        $attempt = $this->mockExam->start($request->user(), $set);

        return redirect($this->sectionRoute($attempt));
    }

    public function listening(Request $request, string $set, MockExamAttempt $attempt): View|RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::LISTENING)) {
            return $redirect;
        }

        return view('mock-exams.listening', [
            'attempt' => $attempt,
            'practiceUrl' => $this->mockExam->listeningPracticeUrl(),
            'total' => $this->mockExam->listeningTotal(),
        ]);
    }

    public function submitListening(Request $request, string $set, MockExamAttempt $attempt): RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::LISTENING)) {
            return $redirect;
        }

        $data = $request->validate([
            'raw' => ['required', 'integer', 'min:0', 'max:'.$this->mockExam->listeningTotal()],
        ]);

        $attempt->forceFill([
            'listening_self_reported_raw' => $data['raw'],
            'listening_total' => $this->mockExam->listeningTotal(),
            'listening_band' => $this->mockExam->listeningBand((int) $data['raw']),
            'current_section' => MockExamAttempt::READING,
            'reading_started_at' => now(),
        ])->save();

        return redirect()->route('mock-tests.reading', [$set, $attempt]);
    }

    public function reading(Request $request, string $set, MockExamAttempt $attempt): View|RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::READING)) {
            return $redirect;
        }

        $passages = $this->mockExam->readingPassages($set)['passages'] ?? [];
        foreach ($passages as &$passage) {
            $passage['html'] = $this->mockExam->renderMarkdown($passage['body']);
        }

        return view('mock-exams.reading', [
            'attempt' => $attempt,
            'passages' => $passages,
            'questions' => $this->mockExam->readingQuestions($set) ?? [],
        ]);
    }

    public function submitReading(Request $request, string $set, MockExamAttempt $attempt): RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::READING)) {
            return $redirect;
        }

        $questions = $this->mockExam->readingQuestions($set) ?? [];
        $chosen = [];
        foreach (array_keys($questions) as $i) {
            $chosen[$i] = (int) $request->input("answers.{$i}", -1);
        }

        $result = $this->quiz->grade($questions, $chosen);

        $attempt->forceFill([
            'reading_answers' => $chosen,
            'reading_correct' => $result['score'],
            'reading_total' => $result['total'],
            'reading_band' => $this->mockExam->readingBand($result['score']),
            'current_section' => MockExamAttempt::WRITING,
            'writing_started_at' => now(),
        ])->save();

        return redirect()->route('mock-tests.writing', [$set, $attempt]);
    }

    public function writing(Request $request, string $set, MockExamAttempt $attempt): View|RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::WRITING)) {
            return $redirect;
        }

        $tasks = $this->mockExam->writingTasks($set);
        $tasks['task1']['prompt_html'] = $this->mockExam->renderMarkdown($tasks['task1']['prompt']);

        return view('mock-exams.writing', [
            'attempt' => $attempt,
            'tasks' => $tasks,
        ]);
    }

    public function submitWriting(Request $request, string $set, MockExamAttempt $attempt): RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::WRITING)) {
            return $redirect;
        }

        $data = $request->validate([
            'task1' => ['nullable', 'string', 'max:6000'],
            'task2' => ['nullable', 'string', 'max:6000'],
        ]);

        $attempt->forceFill([
            'writing_task1_text' => $data['task1'] ?? '',
            'writing_task2_text' => $data['task2'] ?? '',
            'current_section' => MockExamAttempt::SPEAKING,
            'speaking_started_at' => now(),
        ])->save();

        return redirect()->route('mock-tests.speaking', [$set, $attempt]);
    }

    public function speaking(Request $request, string $set, MockExamAttempt $attempt): View|RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::SPEAKING)) {
            return $redirect;
        }

        return view('mock-exams.speaking', [
            'attempt' => $attempt,
            'tasks' => $this->mockExam->speakingTasks($set),
        ]);
    }

    public function submitSpeaking(Request $request, string $set, MockExamAttempt $attempt): RedirectResponse
    {
        if ($redirect = $this->guard($request, $set, $attempt, MockExamAttempt::SPEAKING)) {
            return $redirect;
        }

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:6000']]);

        $attempt->forceFill([
            'speaking_notes' => $data['notes'] ?? '',
            'current_section' => MockExamAttempt::DONE,
            'status' => MockExamAttempt::COMPLETED,
            'completed_at' => now(),
        ])->save();

        return redirect()->route('mock-tests.results', [$set, $attempt]);
    }

    public function results(Request $request, string $set, MockExamAttempt $attempt): View
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        abort_unless($attempt->mock_exam === $set, 404);

        $tasks = $this->mockExam->writingTasks($set);
        $tasks['task1']['prompt_html'] = $this->mockExam->renderMarkdown($tasks['task1']['prompt']);

        return view('mock-exams.results', [
            'attempt' => $attempt,
            'tasks' => $tasks,
            'speakingTasks' => $this->mockExam->speakingTasks($set),
        ]);
    }

    /** Ownership + section-order guard shared by every section's GET/POST. */
    private function guard(Request $request, string $set, MockExamAttempt $attempt, string $expectedSection): ?RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        abort_unless($attempt->mock_exam === $set, 404);

        if ($attempt->current_section !== $expectedSection) {
            return redirect($this->sectionRoute($attempt));
        }

        return null;
    }

    private function sectionRoute(MockExamAttempt $attempt): string
    {
        return match ($attempt->current_section) {
            MockExamAttempt::READING => route('mock-tests.reading', [$attempt->mock_exam, $attempt]),
            MockExamAttempt::WRITING => route('mock-tests.writing', [$attempt->mock_exam, $attempt]),
            MockExamAttempt::SPEAKING => route('mock-tests.speaking', [$attempt->mock_exam, $attempt]),
            MockExamAttempt::DONE => route('mock-tests.results', [$attempt->mock_exam, $attempt]),
            default => route('mock-tests.listening', [$attempt->mock_exam, $attempt]),
        };
    }
}
