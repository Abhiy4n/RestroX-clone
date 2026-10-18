<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Result of a Google OAuth callback. Values are sent to the frontend as the `oauth` query parameter.
 */
enum GoogleAuthOutcome: string
{
    case Success = 'success';
    case Linked = 'linked';
    case Failed = 'failed';
    case UnverifiedEmail = 'unverified_email';
    case LinkRequired = 'link_required';
    case LoginRequired = 'login_required';
    case EmailInUse = 'email_in_use';
    case AccountAlreadyLinked = 'account_already_linked';
    case ProviderAlreadyLinked = 'provider_already_linked';
}
