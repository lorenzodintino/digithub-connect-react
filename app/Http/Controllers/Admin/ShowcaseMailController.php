<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Showcase\SaveShowcaseMail;
use App\Actions\Showcase\TestShowcaseMailer;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowcaseMailRequest;
use App\Models\ShowcaseMailSettings;
use App\Support\MailFailure;
use App\Support\ShowcaseMailData;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShowcaseMailController extends Controller
{
    /**
     * Where the demo requests of the home page go, and how they are sent.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/showcase-mail', [
            'showcase' => ShowcaseMailData::for(ShowcaseMailSettings::current(), $request->user()),
        ]);
    }

    public function update(ShowcaseMailRequest $request, SaveShowcaseMail $save): RedirectResponse
    {
        $save->handle(ShowcaseMailSettings::current(), $request->validated());

        Toast::success(__('Email settings saved.'));

        return back();
    }

    /**
     * Send a test message through the server, which is what puts it in service.
     */
    public function test(Request $request, TestShowcaseMailer $testMailer): RedirectResponse
    {
        $recipient = $request->validate(
            ['test_recipient' => ['required', 'email']],
            attributes: ['test_recipient' => __('recipient')],
        )['test_recipient'];

        $settings = ShowcaseMailSettings::current();

        if (! $settings->hasMailerSettings()) {
            throw ValidationException::withMessages(['mail_host' => __('Fill in server, username and password first, then save.')]);
        }

        ['sent' => $sent, 'error' => $error] = $testMailer->handle($settings, $recipient);

        if ($sent) {
            Toast::success(__('Test email sent to :address.', ['address' => $recipient]));

            return back();
        }

        Toast::error(__('The server refused the message.'));

        throw ValidationException::withMessages(['mail_host' => MailFailure::explain($error) ?? (string) $error]);
    }
}
