<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Database\Eloquent\Model;

class Activity
{
    /**
     * Write one line in the register.
     *
     * Called from the domain actions, never from the screens: the same booking
     * can be cancelled from the agenda, from the customer link or by a command,
     * and the register must read the same in all three cases.
     *
     * The context holds references and numbers. Names, emails and free text of
     * customers stay out of it: a register that quietly becomes a second copy
     * of the personal data would undo the retention rules of the portal.
     *
     * The actor is normally whoever is signed in. Actions that also run from
     * a customer link or from a command say so explicitly instead, because
     * there the session says nothing about who is really acting.
     *
     * @param  array<string, mixed>  $context
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $context = [],
        Tenant|int|null $tenant = null,
        ?string $label = null,
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= self::currentActor();

        // While an administrator is standing in for a manager, the register
        // must say so on every line: that is what makes impersonation
        // acceptable rather than a way of acting as somebody else.
        $behind = app(Impersonation::class)->actor();

        if ($behind !== null && $behind->isNot($actor)) {
            $context['impersonated_by'] = $behind->name;
        }

        $tenantId = match (true) {
            $tenant instanceof Tenant => $tenant->getKey(),
            is_int($tenant) => $tenant,
            default => self::tenantOf($subject) ?? $actor?->tenant_id,
        };

        return ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $actor?->getKey(),
            // Copied so a deleted account does not blank out its own history.
            // `??` already swallows the access on null, so no nullsafe needed here.
            'actor_name' => $actor->name ?? __('The system'),
            'actor_role' => $actor?->role->value,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ?? self::labelOf($subject),
            'context' => $context === [] ? null : $context,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Whoever is signed in, or nobody.
     *
     * Written as its own method because the public pages really do run with
     * nobody there, and the helper is the honest place to say so.
     */
    private static function currentActor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The portal a subject belongs to, when it says so itself.
     */
    private static function tenantOf(?Model $subject): ?int
    {
        if ($subject === null) {
            return null;
        }

        if ($subject instanceof Tenant) {
            return $subject->getKey();
        }

        return $subject->getAttribute('tenant_id');
    }

    /**
     * Something readable about the subject, without opening its row later.
     *
     * Never the customer: a booking is named by its reference, which points at
     * the person without spelling them out.
     */
    private static function labelOf(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        foreach (['name', 'uid', 'label'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
