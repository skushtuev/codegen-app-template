<?php

declare(strict_types=1);

namespace AdminApi\Service;

use Common\App\Models\AdminUser;
use Common\App\Models\Enum\AdminUserRole;

final readonly class AdminAccess
{
    public const ADMIN_USERS = 'adminUsers';
    public const ADMIN_MEDIA = 'adminMedia';
    public const USERS = 'users';

    public function can(AdminUser $user, string $permission): bool
    {
        return match ($permission) {
            self::ADMIN_USERS => $user->getRole() === AdminUserRole::Admin,
            self::ADMIN_MEDIA => true,
            self::USERS => $user->getRole() === AdminUserRole::Admin,
            default => false,
        };
    }

    public function permissions(AdminUser $user): array
    {
        return [
            self::ADMIN_USERS => $this->can($user, self::ADMIN_USERS),
            self::ADMIN_MEDIA => $this->can($user, self::ADMIN_MEDIA),
            self::USERS => $this->can($user, self::USERS),
        ];
    }
}
