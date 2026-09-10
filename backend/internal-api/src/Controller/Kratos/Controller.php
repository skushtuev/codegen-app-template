<?php

declare(strict_types=1);

namespace InternalApi\Controller\Kratos;

use InternalApi\Controller\Kratos\Request\IdentityRequest;
use Common\App\Service\User\Service as UserService;
use Common\Infra\Kratos\Dto\KratosIdentity;
use Common\Infra\Kratos\KratosAdminClient;
use Common\Shared\Http\Exception\NotFoundException;
use Common\Shared\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

/** Receives Kratos web hooks. Guarded by {@see \InternalApi\Middleware\KratosWebhook}. */
final readonly class Controller
{
    public function __construct(
        private ResponseFactory $responseFactory,
        private UserService $userService,
        private KratosAdminClient $kratos,
    ) {}

    /** Registration, settings and verification: mirror the identity. */
    public function sync(IdentityRequest $input): ResponseInterface
    {
        $this->userService->syncFromIdentity($this->mustIdentity($input));

        return $this->responseFactory->noContent();
    }

    /** Login: mirror the identity and stamp `last_login_at`. */
    public function afterLogin(IdentityRequest $input): ResponseInterface
    {
        $this->userService->recordLogin($this->mustIdentity($input));

        return $this->responseFactory->noContent();
    }

    private function mustIdentity(IdentityRequest $input): KratosIdentity
    {
        return $this->kratos->getIdentity($input->identityId()) ?? throw new NotFoundException();
    }
}
