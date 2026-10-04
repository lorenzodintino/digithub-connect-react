<?php

namespace App\Enums;

/**
 * The documents the platform keeps towards the businesses it serves.
 *
 * These are ours, not the portals': each portal still publishes its own
 * privacy policy and terms for its customers.
 */
enum LegalDocumentType: string
{
    case DataProcessingAgreement = 'dpa';
    case SubProcessors = 'sub_processors';
    case PrivacyPolicy = 'privacy_policy';
    case Terms = 'terms';

    public function label(): string
    {
        return match ($this) {
            self::DataProcessingAgreement => __('Data processing agreement'),
            self::SubProcessors => __('Sub-processors'),
            self::PrivacyPolicy => __('Platform privacy policy'),
            self::Terms => __('Platform terms of service'),
        };
    }

    /**
     * Whether each portal has to accept every new version.
     *
     * The agreement and the terms bind the portal. The privacy policy and the
     * list of sub-processors are there to be read: the portal must be told
     * when they change, not asked to sign them.
     */
    public function requiresAcceptance(): bool
    {
        return match ($this) {
            self::DataProcessingAgreement, self::Terms => true,
            self::SubProcessors, self::PrivacyPolicy => false,
        };
    }
}
