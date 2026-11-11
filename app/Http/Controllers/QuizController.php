<?php

namespace App\Http\Controllers;

use App\Services\Exam;
use App\Services\Learning;
use App\Services\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Chapter quizzes. Anyone may take one — reading and practising stay public — but only a
 * logged-in reader gets the result saved. Submitting redirects back to the quiz with the
 * picked answers flashed once, so a refresh never re-sends the form.
 */
class QuizController extends Controller
{
    public function __construct(private Quiz $quiz, private Learning $learning, private Exam $exam) {}

    public function show(Request $request, string $course, string $chapter): View
    {
        $questions = $this->questions($course, $chapter);
        $user = $request->user();

        $lang = $request->query('lang') === 'bn' && $this->quiz->hasBangla($questions) ? 'bn' : 'en';

        // Present only on the request right after a submit.
        $picked = session('quiz_picked');
        $result = is_array($picked) && count($picked) === count($questions)
            ? $this->quiz->grade($questions, $picked)
            : null;

        $order = array_keys($this->learning->catalog()[$course]);
        $next = $order[array_search($chapter, $order, true) + 1] ?? null;

        return view('quiz.show', [
            'quiz' => $this->quiz,
            'questions' => $questions,
            'lang' => $lang,
            'hasBn' => $this->quiz->hasBangla($questions),
            'result' => $result,
            'saved' => $result && $user && session('quiz_saved'),
            'best' => $user && ! $result ? $this->quiz->best($user, $course, $chapter) : null,
            'chapterInfo' => $this->learning->describe($course, $chapter),
            'next' => $next ? $this->learning->describe($course, $next) : null,
            'examUrl' => ! $next && $this->exam->exists($course) ? route('exam.show', $course) : null,
        ]);
    }

    public function submit(Request $request, string $course, string $chapter): RedirectResponse
    {
        $questions = $this->questions($course, $chapter);

        $back = route('quiz.show', [$course, $chapter]).($request->input('lang') === 'bn' ? '?lang=bn' : '');

        $rules = ['answers' => ['required', 'array']];
        foreach ($questions as $i => $question) {
            $rules["answers.{$i}"] = ['required', 'integer', 'between:0,'.(count($question['options']) - 1)];
        }

        $validator = Validator::make($request->all(), $rules, [
            'answers.required' => 'Pick an answer for every question.',
            'answers.*.required' => 'Pick an answer.',
            'answers.*.integer' => 'Pick one of the options.',
            'answers.*.between' => 'Pick one of the options.',
        ]);

        if ($validator->fails()) {
            return redirect($back.'#quiz')->withErrors($validator)->withInput();
        }

        $picked = array_map(fn (int $i) => (int) $request->input("answers.{$i}"), array_keys($questions));
        $result = $this->quiz->grade($questions, $picked);

        $user = $request->user();
        if ($user) {
            $this->quiz->record($user, $course, $chapter, $result);
        }

        return redirect($back.'#result')
            ->with('quiz_picked', $picked)
            ->with('quiz_saved', (bool) $user);
    }

    /** @return list<array> */
    private function questions(string $course, string $chapter): array
    {
        return $this->quiz->questions($course, $chapter) ?? abort(404);
    }
}
