<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MerchantPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Merchant $merchant): bool|Response
    {
        if ((int) $merchant->user_id !== (int) $user->id) {
            return Response::denyAsNotFound();
        }

        return $user->is_active;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Merchant $merchant): bool|Response
    {
        return $this->view($user, $merchant);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Merchant $merchant): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Merchant $merchant): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Merchant $merchant): bool
    {
        return false;
    }
}
