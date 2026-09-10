<?php

declare(strict_types=1);

namespace Common\App\Service\User;

use Common\App\Models\User;
use Common\App\Repository\UserRepository;
use Common\App\Service\AbstractService;
use Common\Infra\Kratos\Dto\KratosIdentity;
use Common\Infra\Kratos\KratosAdminClient;
use Common\Shared\Http\PaginationRequest;
use Common\Shared\Http\PaginationResponse;
use Common\Shared\ValueObject\NullableText;
use Common\Shared\ValueObject\Uuid;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final readonly class Service extends AbstractService
{
    /** Upper bound for one sync run. Kratos has no "changed since" filter, so a full
     *  read of a large instance would be slow; anything beyond this is left to the webhook. */
    public const SYNC_LIMIT = 1000;

    public function __construct(
        LoggerInterface $logger,
        private UserRepository $userRepo,
        private KratosAdminClient $kratos,
    ) {
        parent::__construct($logger);
    }

    /**
     * Creates or updates the local mirror of a Kratos identity.
     *
     * Safe to run twice: `identity_id` is unique and this is an upsert, so a webhook
     * delivered twice changes nothing.
     */
    public function syncFromIdentity(KratosIdentity $identity): User
    {
        $user = $this->userRepo->getOneByIdentityId($identity->id) ?? $this->userRepo->getEmptyModel();

        if ($user->isNew()) {
            $user->setIdentityId($identity->id);
        }

        $user->setEmail($identity->email);
        $user->setName($identity->name);
        $user->setLanguage($identity->language);
        // Kratos reports a bool, we store a timestamp. Keep the one we already have.
        $user->setEmailVerifiedAt(
            $identity->emailVerified ? ($user->getEmailVerifiedAt() ?? new DateTimeImmutable()) : null,
        );

        return $this->userRepo->save($user);
    }

    /** Same as a sync, plus the login stamp. Kratos never sends us this, only the hook knows. */
    public function recordLogin(KratosIdentity $identity): User
    {
        $user = $this->syncFromIdentity($identity);
        $user->setLastLoginAt(new DateTimeImmutable());

        return $this->userRepo->save($user);
    }

    public function getList(PaginationRequest $pagination): PaginationResponse
    {
        return PaginationResponse::fromPagination(
            data: $this->userRepo->getList($pagination),
            count: $this->userRepo->count(),
            pagination: $pagination,
        );
    }

    public function getOneById(Uuid $id): ?User
    {
        return $this->userRepo->getOneById($id);
    }

    /**
     * A local flag is not enough: the user still holds a valid session cookie. Kratos must
     * deactivate the identity, which also kills the sessions.
     */
    public function ban(Uuid $id, NullableText $reason): ?User
    {
        return $this->setBanned($id, new DateTimeImmutable(), $reason);
    }

    public function unban(Uuid $id): ?User
    {
        return $this->setBanned($id, null, new NullableText(null));
    }

    private function setBanned(Uuid $id, ?DateTimeImmutable $bannedAt, NullableText $reason): ?User
    {
        if (!$user = $this->getOneById($id)) {
            return null;
        }

        return $this->transaction(function () use ($user, $bannedAt, $reason): User {
            $user->setBannedAt($bannedAt);
            $user->setBanReason($reason);
            $saved = $this->userRepo->save($user);
            // Kratos last: if it fails, the transaction rolls back and nothing changed.
            $this->kratos->setActive($user->getIdentityId(), $bannedAt === null);

            return $saved;
        });
    }

    /**
     * Mirrors Kratos identities. Repairs rows lost when a webhook could not be delivered.
     *
     * @return int number of identities synced
     */
    public function syncAllFromKratos(int $perPage = 100, int $limit = self::SYNC_LIMIT): int
    {
        $synced = 0;

        for ($page = 1; $page * $perPage <= $limit; $page++) {
            $identities = $this->kratos->listIdentities($page, $perPage);

            if ($identities === []) {
                break;
            }

            foreach ($identities as $identity) {
                $this->syncFromIdentity($identity);
                $synced++;
            }

            if (count($identities) < $perPage) {
                break;
            }
        }

        return $synced;
    }
}
