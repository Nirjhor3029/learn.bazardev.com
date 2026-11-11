<?php

namespace App\Http\Controllers;

use App\Models\ChapterState;
use App\Models\Contribution;
use App\Models\Note;
use App\Services\Learning;
use App\Services\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Everything personal to a logged-in reader: done / bookmark / notes and the
 * "My Learning" page. Each action answers JSON to the page's fetch() calls and
 * falls back to a normal redirect when JavaScript is off.
 */
class LearningController extends Controller
{
    public function __construct(private Learning $learning, private Quiz $quiz) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $states = $this->learning->states($user);
        $progress = $this->learning->progress($user);

        $bookmarks = $states
            ->filter(fn (ChapterState $state) => $state->bookmarked_at)
            ->sortByDesc('bookmarked_at')
            ->map(fn (ChapterState $state) => [
                ...$this->learning->describe($state->course, $state->chapter),
                'done' => (bool) $state->completed_at,
            ])
            ->values();

        // Notes are kept even if a chapter is later removed — they are the reader's own writing.
        $notes = $user->notes()->latest('updated_at')->get()
            ->map(fn (Note $note) => [
                'note' => $note,
                'chapter' => $this->learning->describe($note->course, $note->chapter),
            ]);

        $contributions = $user->contributions()->latest()->get()
            ->each(fn (Contribution $item) => $item->setAttribute('place', $this->learning->describe($item->course, $item->chapter)));

        return view('learning.dashboard', [
            'user' => $user,
            'courses' => config('course.courses'),
            'progress' => $progress,
            'resume' => $this->learning->resume($user),
            'start' => $this->learning->firstChapter(),
            'bookmarks' => $bookmarks,
            'notes' => $notes,
            'contributions' => $contributions,
            'quizzes' => $this->quiz->courseCounts($user),
            'stats' => [
                'done' => array_sum(array_column($progress, 'done')),
                'total' => array_sum(array_column($progress, 'total')),
                'coursesDone' => count(array_filter($progress, fn (array $p) => $p['done'] === $p['total'])),
                'coursesTotal' => count($progress),
            ],
        ]);
    }

    public function done(Request $request, string $course, string $chapter): JsonResponse|RedirectResponse
    {
        $this->guard($course, $chapter);

        $done = $this->wanted($request);
        $this->learning->setDone($request->user(), $course, $chapter, $done);

        if ($request->expectsJson()) {
            return response()->json([
                'on' => $done,
                'progress' => $this->learning->courseProgress($request->user(), $course),
            ]);
        }

        return back()->with('status', $done ? 'Chapter marked as done.' : 'Chapter marked as not done.');
    }

    public function bookmark(Request $request, string $course, string $chapter): JsonResponse|RedirectResponse
    {
        $this->guard($course, $chapter);

        $bookmarked = $this->wanted($request);
        $this->learning->setBookmarked($request->user(), $course, $chapter, $bookmarked);

        if ($request->expectsJson()) {
            return response()->json(['on' => $bookmarked]);
        }

        return back()->with('status', $bookmarked ? 'Bookmark saved.' : 'Bookmark removed.');
    }

    /** Saving an empty note deletes it. */
    public function note(Request $request, string $course, string $chapter): JsonResponse|RedirectResponse
    {
        $this->guard($course, $chapter);

        // Browsers send textarea line breaks as \r\n; count and store them as one character.
        $request->merge(['body' => str_replace("\r\n", "\n", (string) $request->input('body'))]);

        $body = trim((string) $request->validate([
            'body' => ['nullable', 'string', 'max:'.Note::MAX_LENGTH],
        ])['body']);

        $notes = $request->user()->notes();
        $key = ['course' => $course, 'chapter' => $chapter];

        if ($body === '') {
            $notes->where($key)->delete();
        } else {
            $notes->updateOrCreate($key, ['body' => $body]);
        }

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'empty' => $body === '']);
        }

        return back()->with('status', $body === '' ? 'Note removed.' : 'Note saved.');
    }

    private function guard(string $course, string $chapter): void
    {
        abort_unless($this->learning->exists($course, $chapter), 404);
    }

    /** The page always says which state it wants (value=1 or 0), so a double click can never flip it twice. */
    private function wanted(Request $request): bool
    {
        return $request->validate(['value' => ['required', 'boolean']])['value'] == true;
    }
}
