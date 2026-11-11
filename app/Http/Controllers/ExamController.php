<?php

namespace App\Http\Controllers;

use App\Services\Exam;
use App\Services\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * The final exam of a course. Logged-in readers only (the result and the certificate belong
 * to an account), and only once every chapter quiz is passed. A sitting is drawn at random
 * and remembered in the session by position, so the browser never says which questions to
 * grade. The result is flashed once; a refresh gives a fresh form with a new draw.
 */
class ExamController extends Controller
{
    public function __construct(private Exam $exam, private Quiz $quiz) {}

    public function show(Request $request, string $course): View
    {
        $pool = $this->pool($course);
        $user = $request->user();

        $result = session('exam_result');
        $remaining = $this->exam->remaining($user, $course);

        $questions = null;
        if (! is_array($result) && $remaining === []) {
            $ids = session("exam_draw.{$course}");
            $questions = is_array($ids) ? $this->exam->questionsFor($course, $ids) : null;

            if ($questions === null) {
                $ids = $this->exam->draw($course);
                session(["exam_draw.{$course}" => $ids]);
                $questions = $this->exam->questionsFor($course, $ids);
            }
        }

        $hasBn = $this->quiz->hasBangla($pool, explained: false);

        return view('exam.show', [
            'quiz' => $this->quiz,
            'course' => $course,
            'courseTitle' => config("course.courses.{$course}.title"),
            'questions' => $questions,
            'result' => is_array($result) ? $result : null,
            'remaining' => $remaining,
            'lang' => $request->query('lang') === 'bn' && $hasBn ? 'bn' : 'en',
            'hasBn' => $hasBn,
            'need' => $this->exam->passMark(),
            'total' => Exam::QUESTIONS,
            'best' => $this->exam->best($user, $course),
            'certificate' => $this->exam->certificate($user, $course),
        ]);
    }

    public function submit(Request $request, string $course): RedirectResponse
    {
        $this->pool($course);
        $user = $request->user();

        $back = route('exam.show', $course).($request->input('lang') === 'bn' ? '?lang=bn' : '');

        if ($this->exam->remaining($user, $course) !== []) {
            return redirect($back);
        }

        $ids = session("exam_draw.{$course}");
        $questions = is_array($ids) ? $this->exam->questionsFor($course, $ids) : null;

        if ($questions === null) {
            // The session lost the sitting (expired, or two tabs). Nothing is graded; a new one is drawn.
            session()->forget("exam_draw.{$course}");

            return redirect($back)->with('exam_expired', true);
        }

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
            return redirect($back.'#exam')->withErrors($validator)->withInput();
        }

        $picked = array_map(fn (int $i) => (int) $request->input("answers.{$i}"), array_keys($questions));
        $result = $this->exam->grade($course, $questions, $picked);

        $this->exam->record($user, $course, $result);
        $certificate = $result['passed'] ? $this->exam->issue($user, $course, $result) : null;

        // This sitting is over; the next visit draws a new one.
        session()->forget("exam_draw.{$course}");

        return redirect($back.'#result')->with('exam_result', [...$result, 'certificate' => $certificate?->code]);
    }

    /** @return list<array> */
    private function pool(string $course): array
    {
        return $this->exam->pool($course) ?? abort(404);
    }
}
