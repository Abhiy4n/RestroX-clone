<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Removes formatting from a phone number, e.g. "+977 981-234-5678" becomes "+9779812345678".
 */
class NormalizePhone
{
    /**
     * Keep only "+" and digits. Empty strings become null; non-strings are returned unchanged
     * so validation can reject them.
     */
    public function handle(mixed $phone): mixed
    {
        if (! is_string($phone)) {
            return $phone;
        }

        if (trim($phone) === '') {
            return null;
        }

        return preg_replace('/[^+\d]/u', '', $phone);
    }
}
