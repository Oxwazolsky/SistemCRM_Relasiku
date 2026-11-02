<?php

namespace App\Policies;

use App\Models\User;
use App\Services\CrmAccess;
use Illuminate\Database\Eloquent\Model;

/** Object-level authorization for CRM records. */
class CrmPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CrmAccess::class)->role($user, 'admin', 'owner', 'cs');
    }

    public function view(User $user, Model $record): bool
    {
        return app(CrmAccess::class)->canRead($user, $record);
    }

    public function create(User $user): bool
    {
        return app(CrmAccess::class)->role($user, 'owner', 'cs');
    }

    public function update(User $user, Model $record): bool
    {
        return app(CrmAccess::class)->canWrite($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        // Historical records are never deleted in the MVP.
        return false;
    }
}
