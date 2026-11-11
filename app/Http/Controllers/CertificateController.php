<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The public page of a certificate: anyone with the link can open it to check it is real.
 * Only the owner can ask for the printed name to follow their account name again.
 */
class CertificateController extends Controller
{
    public function show(Request $request, string $code): Response
    {
        $certificate = $this->find($code);

        return response()
            ->view('certificate.show', [
                'certificate' => $certificate,
                'isOwner' => $request->user()?->id === $certificate->user_id,
                'accountName' => $request->user()?->name,
            ])
            // A name is on this page — keep it out of search engines; the link is the proof.
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** The printed name is a copy taken when the certificate was issued; this refreshes it. */
    public function refreshName(Request $request, string $code): RedirectResponse
    {
        $certificate = $this->find($code);

        abort_unless($request->user()->id === $certificate->user_id, 404);

        $certificate->forceFill(['name' => Str::limit(trim($request->user()->name), 120, '')])->save();

        return back()->with('status', 'The name on your certificate now matches your account.');
    }

    private function find(string $code): Certificate
    {
        return Certificate::where('code', Str::upper($code))->firstOrFail();
    }
}
