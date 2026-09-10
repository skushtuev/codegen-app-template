<?php

declare(strict_types=1);

namespace Common\App\Models;

use Common\Shared\Enum\AppLanguage;
use Common\Shared\ValueObject\Email;
use Common\Shared\ValueObject\NullableText;
use Common\Shared\ValueObject\Uuid;
use DateTimeImmutable;
use LogicException;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

/** End user. Kratos owns the credentials; this is our mirror, linked by `identity_id`. */
final class User extends AbstractModel
{
    use PrivatePropertiesTrait;

    private string $id;
    private string $identity_id;
    private string $email;
    private ?string $name = null;
    private string $language;
    private ?DateTimeImmutable $email_verified_at = null;
    private ?DateTimeImmutable $last_login_at = null;
    private ?DateTimeImmutable $banned_at = null;
    private ?string $ban_reason = null;
    private ?DateTimeImmutable $created_at = null;
    private ?DateTimeImmutable $updated_at = null;

    public function tableName(): string
    {
        return '{{%users}}';
    }

    public function getId(): Uuid
    {
        return new Uuid($this->id);
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id->value();
    }

    public function getIdentityId(): Uuid
    {
        return new Uuid($this->identity_id);
    }

    public function setIdentityId(Uuid $identityId): void
    {
        $this->identity_id = $identityId->value();
    }

    public function getEmail(): Email
    {
        return new Email($this->email);
    }

    public function setEmail(Email $email): void
    {
        $this->email = $email->value();
    }

    public function getName(): NullableText
    {
        return new NullableText($this->name);
    }

    public function setName(NullableText $name): void
    {
        $this->name = $name->value();
    }

    public function getLanguage(): AppLanguage
    {
        return AppLanguage::from($this->language);
    }

    public function setLanguage(AppLanguage $language): void
    {
        $this->language = $language->value;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->email_verified_at;
    }

    public function setEmailVerifiedAt(?DateTimeImmutable $emailVerifiedAt): void
    {
        $this->email_verified_at = $emailVerifiedAt;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->last_login_at;
    }

    public function setLastLoginAt(?DateTimeImmutable $lastLoginAt): void
    {
        $this->last_login_at = $lastLoginAt;
    }

    public function getBannedAt(): ?DateTimeImmutable
    {
        return $this->banned_at;
    }

    public function setBannedAt(?DateTimeImmutable $bannedAt): void
    {
        $this->banned_at = $bannedAt;
    }

    public function getBanReason(): NullableText
    {
        return new NullableText($this->ban_reason);
    }

    public function setBanReason(NullableText $banReason): void
    {
        $this->ban_reason = $banReason->value();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at ?? throw new LogicException('User createdAt is not set.');
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updated_at ?? throw new LogicException('User updatedAt is not set.');
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }
}
