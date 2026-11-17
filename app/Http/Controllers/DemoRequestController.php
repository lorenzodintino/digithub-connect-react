<?php

namespace App\Http\Controllers;

use App\Http\Requests\DemoRequestRequest;
use App\Mail\DemoRequestMail;
use App\Models\ShowcaseMailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class DemoRequestController extends Controller
{
    /**
     * Take a demo request from the home page and pass it to the administrators.
     *
     * Answers JSON: the form of the showcase sits on a page of its own and
     * only needs to know whether the request went.
     */
    public function __invoke(DemoRequestRequest $request): JsonResponse
    {
        $recipient = ShowcaseMailSettings::current()->recipientAddress();

        abort_if($recipient === null, 404);

        // A robot filled the trap field: it gets the same answer, and nothing leaves.
        if (filled($request->validated('website'))) {
            return response()->json(['sent' => true]);
        }

        $mail = new DemoRequestMail(
            requesterName: $request->validated('name'),
            requesterEmail: $request->validated('email'),
            requesterBusiness: $request->validated('business') ?: null,
            requestedPlan: $request->validated('plan') ?: null,
            requesterMessage: $request->validated('message') ?: null,
            pageLanguage: app()->getLocale(),
            consentGivenAt: now()->translatedFormat('d M Y, H:i'),
        );

        Mail::to($recipient)->locale(config('app.locale'))->send($mail);

        return response()->json(['sent' => true]);
    }
}
