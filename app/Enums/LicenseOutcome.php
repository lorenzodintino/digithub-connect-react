<?php

namespace App\Enums;

/**
 * What DigitHub License Manager said about a portal, already interpreted.
 *
 * `Unavailable` is never stored: it is what the platform knows when the
 * License Manager has never answered for the portal.
 */
enum LicenseOutcome: string
{
    /** A licence exists: `access.allowed` says whether it may be used. */
    case Licensed = 'licensed';

    /** The portal is not registered (404 tenant_not_found) or has no licence yet (404 no_license). */
    case NotActivated = 'not_activated';

    /** The software is disabled in the License Manager (403 software_inactive). */
    case SoftwareInactive = 'software_inactive';

    /** No answer has ever been received. */
    case Unavailable = 'unavailable';
}
