<?php

namespace App\Policies;

use App\Models\Sobre;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SobrePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, ['admin', 'tesorero']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sobre $sobre): bool
    {
        return in_array($user->rol, ['admin', 'tesorero']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->rol, ['admin', 'tesorero']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sobre $sobre): bool
    {
        return in_array($user->rol, ['admin', 'tesorero']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sobre $sobre): bool
    {
        return in_array($user->rol, ['admin', 'tesorero']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sobre $sobre): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sobre $sobre): bool
    {
        return false;
    }
}
