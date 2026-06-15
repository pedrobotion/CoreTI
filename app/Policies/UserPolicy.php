<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAdminDashboard(User $authUser): bool
    {
        return $authUser->role === 'admin';
    }

    public function approve(User $authUser, User $targetUser): bool
    {
        return $this->canManageOtherUser($authUser, $targetUser);
    }

    public function reject(User $authUser, User $targetUser): bool
    {
        return $this->canManageOtherUser($authUser, $targetUser);
    }

    public function resetPassword(User $authUser, User $targetUser): bool
    {
        return $this->canManageOtherUser($authUser, $targetUser);
    }

    public function updateRole(User $authUser, User $targetUser): bool
    {
        return $this->canManageOtherUser($authUser, $targetUser);
    }

    public function delete(User $authUser, User $targetUser): bool
    {
        return $this->canManageOtherUser($authUser, $targetUser);
    }

    private function canManageOtherUser(User $authUser, User $targetUser): bool
    {
        if ($targetUser->isMasterAccount()) {
            return false;
        }

        return $authUser->role === 'admin' && $authUser->id !== $targetUser->id;
    }
}
