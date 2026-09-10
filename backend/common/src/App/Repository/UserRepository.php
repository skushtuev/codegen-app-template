<?php

declare(strict_types=1);

namespace Common\App\Repository;

use Common\App\Models\User;
use Common\Shared\Http\PaginationRequest;
use Common\Shared\Util\Uuid as UuidUtil;
use Common\Shared\ValueObject\Email;
use Common\Shared\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class UserRepository
{
    public function __construct(
        private User $model,
        private UuidUtil $uuid,
    ) {}

    public function getEmptyModel(): User
    {
        return new User();
    }

    public function save(User $model): User
    {
        if ($model->isNew()) {
            $model->setId($this->uuid->generate());
            $model->setCreatedAt(new DateTimeImmutable());
        }

        $model->setUpdatedAt(new DateTimeImmutable());
        $model->save();
        $model->refresh();

        return $model;
    }

    public function getOneById(Uuid $id): ?User
    {
        return $this->model->query()
            ->where([
                'id' => $id->value(),
            ])
            ->limit(1)
            ->one();
    }

    /** Finds a user by Kratos identity id. */
    public function getOneByIdentityId(Uuid $identityId): ?User
    {
        return $this->model->query()
            ->where([
                'identity_id' => $identityId->value(),
            ])
            ->limit(1)
            ->one();
    }

    public function getOneByEmail(Email $email): ?User
    {
        return $this->model->query()
            ->where([
                'email' => $email->value(),
            ])
            ->limit(1)
            ->one();
    }

    /**
     * @return User[]
     */
    public function getList(PaginationRequest $pagination): array
    {
        return $this->model->query()
            ->orderBy(['created_at' => \SORT_DESC])
            ->limit($pagination->limit())
            ->offset($pagination->offset())
            ->all();
    }

    public function count(): int
    {
        return (int) $this->model->query()->count();
    }
}
