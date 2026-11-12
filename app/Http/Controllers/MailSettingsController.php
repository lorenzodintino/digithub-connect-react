<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\SaveMailSettings;
use App\Actions\Tenants\TestMailer;
use App\Http\Requests\MailSettingsRequest;
use App\Support\MailFailure;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MailSettingsController extends Controller
{
    /**
     * Save the email delivery of a portal.
     */
    public function update(MailSettingsRequest $request, SaveMailSettings $save): RedirectResponse
    {
        $tenant = $request->tenant();

        $save->handle($tenant, $request->validated(), Gate::allows('configureMailer', $tenant));

        Toast::success(__('Email settings saved.'));

        return back();
    }

    /**
     * Send a test message through the portal's own server, which is what puts
     * it in service.
     */
    public function test(MailSettingsRequest $request, TestMailer $testMailer): RedirectResponse
    {
        $tenant = $request->tenant();

        $recipient = $request->validate(
            ['test_recipient' => ['required', 'email']],
            attributes: ['test_recipient' => __('recipient')],
        )['test_recipient'];

        if (! $tenant->hasMailerSettings()) {
            throw ValidationException::withMessages(['mail_host' => __('Fill in server, username and password first, then save.')]);
        }

        ['sent' => $sent, 'error' => $error] = $testMailer->handle($tenant, $recipient);

        if ($sent) {
            Toast::success(__('Test email sent to :address.', ['address' => $recipient]));

            return back();
        }

        Toast::error(__('The server refused the message.'));

        throw ValidationException::withMessages(['mail_host' => MailFailure::explain($error) ?? (string) $error]);
    }
}
