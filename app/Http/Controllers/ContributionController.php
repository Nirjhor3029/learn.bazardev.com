<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Services\Learning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Readers send in a resource, a tip or a mistake report for a chapter.
 * Everything starts as "pending"; only an admin can make it public.
 */
class ContributionController extends Controller
{
    public function __construct(private Learning $learning) {}

    public function store(Request $request, string $course, string $chapter): RedirectResponse
    {
        abort_unless($this->learning->exists($course, $chapter), 404);

        $user = $request->user();

        // Back to the form (not the top of a long chapter), in the language the reader was using.
        $back = route('course.chapter', [$course, $chapter]).($request->input('lang') === 'bn' ? '?lang=bn' : '').'#contribute';

        $input = [
            'type' => $request->input('type'),
            'title' => $this->oneLine($request->input('title')),
            'url' => trim((string) $request->input('url')),
            // Called "details" in the form so it never mixes with the private note box (also on this page).
            'details' => trim(str_replace("\r\n", "\n", (string) $request->input('details'))),
        ];

        $validator = Validator::make($input, [
            'type' => ['required', Rule::in(Contribution::TYPES)],
            'title' => ['exclude_unless:type,resource', 'required', 'string', 'max:'.Contribution::TITLE_MAX],
            'url' => ['exclude_unless:type,resource', 'required', 'string', 'max:500', 'url:http,https'],
            'details' => ['required_unless:type,resource', 'nullable', 'string', 'max:'.Contribution::BODY_MAX],
        ], [
            'type.*' => 'Choose what you want to share.',
            'title.required' => 'Give the resource a short title.',
            'url.required' => 'Paste the link to the resource.',
            'url.url' => 'That does not look like a link. It should start with https://',
            'details.required_unless' => 'Write a few words first.',
            'details.max' => 'Please keep it under '.Contribution::BODY_MAX.' characters.',
            'title.max' => 'Please keep the title under '.Contribution::TITLE_MAX.' characters.',
        ]);

        $validator->after(function ($validator) use ($user, $course, $chapter, $input) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $user->is_admin && $user->contributions()->where('created_at', '>', now()->subDay())->count() >= Contribution::DAILY_LIMIT) {
                $validator->errors()->add('limit', 'You have sent '.Contribution::DAILY_LIMIT.' suggestions in the last 24 hours. Please come back tomorrow — thank you for helping!');

                return;
            }

            $alreadyThere = $input['type'] === 'resource' && Contribution::query()
                ->where(['course' => $course, 'chapter' => $chapter, 'url' => $input['url']])
                ->where('status', '!=', Contribution::REJECTED)
                ->exists();

            if ($alreadyThere) {
                $validator->errors()->add('url', 'This link has already been suggested for this chapter.');
            }
        });

        if ($validator->fails()) {
            return redirect()->to($back)->withErrors($validator, 'contribution')->withInput();
        }

        $data = $validator->validated();

        $user->contributions()->create([
            'course' => $course,
            'chapter' => $chapter,
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'url' => $data['url'] ?? null,
            'body' => ($data['details'] ?? '') === '' ? null : $data['details'],
        ]);

        return redirect()->to($back)->with('status', $data['type'] === 'correction'
            ? 'Thank you! Your report was sent to the admin.'
            : 'Thank you! Your suggestion was sent for review. It will appear here once an admin approves it.');
    }

    /** The sender can take back anything they sent — also after it was published. */
    public function destroy(Request $request, Contribution $contribution): RedirectResponse
    {
        abort_unless($contribution->user_id === $request->user()->id, 404);

        $contribution->delete();

        return back()->with('status', 'Your contribution was removed.');
    }

    private function oneLine(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
