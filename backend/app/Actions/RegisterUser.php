<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new user account from validated sign-up data.
 */
class RegisterUser
{
    /**
     * Save the new user and send them an email verification link.
     *
     * The password is hashed automatically by the `hashed` cast on the User model.
     *
     * @param  array{name: string, email: string, phone_country_iso2?: ?string, phone_e164?: ?string, password: string}  $data
     */
    public function handle(array $data): User
    {
        // If the database fails, record the real error for us and show the user a
        // friendly message instead of a raw database error.
        try {
            $user = DB::transaction(function () use ($data): User {

                // Create the user account.
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone_country_iso2' => $data['phone_country_iso2'] ?? null,
                    'phone_e164' => $data['phone_e164'] ?? null,
                    'password' => $data['password'],
                ]);

                // Automatically create a wallet.
                $user->wallet()->create([
                    'currency' => 'NPR',
                ]);

                return $user;
            });
        } catch (QueryException $exception) {
            report($exception);

            throw new HttpException(500, 'Unable to create your account right now. Please try again later.');
        }

        $user->sendEmailVerificationNotification();
        Log::info('User account ad n registered.', ['user_id' => $user->getKey()]);

        return $user;
    }
}
