<?php

declare(strict_types=1);

namespace AdminApi\Controller\Users\Response;

use Common\App\Models\User;
use DateTimeInterface;

final class UserResponse
{
    public function __construct(
        public string $id,
        public string $identityId,
        public string $email,
        public ?string $name,
        public string $language,
        public bool $emailVerified,
        public ?string $lastLoginAt,
        public ?string $bannedAt,
        public ?string $banReason,
        public string $createdAt,
    ) {}

    public static function fromModel(User $model): self
    {
        return new self(
            id: $model->getId()->value(),
            identityId: $model->getIdentityId()->value(),
            email: $model->getEmail()->value(),
            name: $model->getName()->value(),
            language: $model->getLanguage()->value,
            emailVerified: $model->getEmailVerifiedAt() !== null,
            lastLoginAt: $model->getLastLoginAt()?->format(DateTimeInterface::ATOM),
            bannedAt: $model->getBannedAt()?->format(DateTimeInterface::ATOM),
            banReason: $model->getBanReason()->value(),
            createdAt: $model->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }
}
