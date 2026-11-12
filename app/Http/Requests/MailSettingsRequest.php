<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MailSettingsRequest extends FormRequest
{
    private ?Tenant $resolvedTenant = null;

    /**
     * The portal whose email delivery is being saved: the one in the URL for
     * the administrator, the manager's own otherwise.
     *
     * The controller is shared by both routes and takes no Tenant argument, so
     * implicit binding never runs: the `{tenant}` parameter arrives as the slug.
     */
    public function tenant(): Tenant
    {
        if ($this->resolvedTenant !== null) {
            return $this->resolvedTenant;
        }

        $slug = $this->route('tenant');

        if ($slug instanceof Tenant) {
            return $this->resolvedTenant = $slug;
        }

        if (is_string($slug)) {
            return $this->resolvedTenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        }

        /** @var User $user */
        $user = $this->user();

        abort_if($user->tenant === null, 404);

        return $this->resolvedTenant = $user->tenant;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->tenant()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor(Gate::forUser($this->user())->allows('configureMailer', $this->tenant()));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::attributeNames();
    }

    /**
     * The server fields only exist for whoever may configure the server.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(bool $canConfigure): array
    {
        $serverRules = $canConfigure ? [
            'mail_host' => ['nullable', 'string', 'max:255', self::hostRule()],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
        ] : [];

        return $serverRules + [
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'notify_manager_on_booking' => ['boolean'],
            'reminder_hours_before' => ['nullable', 'integer', 'min:1', 'max:336'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'mail_host' => __('server'),
            'mail_port' => __('port'),
            'mail_username' => __('username'),
            'mail_password' => __('password'),
            'mail_from_address' => __('sender address'),
            'reminder_hours_before' => __('reminder lead time'),
            'reply_to_email' => __('reply address'),
        ];
    }

    /**
     * Reject anything that is not the name of a mail server.
     *
     * The address of the mailbox in the server field is the mistake everybody
     * makes, and the transport reports it as an unresolvable host name — which
     * reads like a network problem rather than a filled-in-wrong field.
     */
    public static function hostRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (str_contains($value, '@')) {
                $fail(__('The SMTP server is the name of a machine, like smtp.example.com — not an email address. The address goes in the username field below.'));

                return;
            }

            if (preg_match('~[\s/:]~', $value) === 1) {
                $fail(__('Only the server name here: no http://, no port, no spaces.'));

                return;
            }

            if (filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                $fail(__('This does not look like a server name.'));
            }
        };
    }
}
