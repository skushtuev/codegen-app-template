<?php

declare(strict_types=1);

namespace AdminApi\Controller\Users;

use AdminApi\Controller\AbstractController;
use AdminApi\Controller\Users\Request\BanRequest;
use AdminApi\Controller\Users\Request\ListRequest;
use AdminApi\Controller\Users\Response\UserResponse;
use Common\App\Service\User\Service as UserService;
use Common\Shared\Http\Exception\NotFoundException;
use Common\Shared\Http\PaginationRequest;
use Common\Shared\Http\PaginationResponse;
use Common\Shared\Http\ResponseFactory;
use Common\Shared\ValueObject\Uuid;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class Controller extends AbstractController
{
    public function __construct(
        private ResponseFactory $responseFactory,
        private UserService $userService,
    ) {}

    public function list(ListRequest $input): ResponseInterface
    {
        $pagination = $this->userService->getList(new PaginationRequest(
            page: $input->page(),
            perPage: $input->perPage(),
        ));

        return $this->responseFactory->ok(new PaginationResponse(
            data: array_map(UserResponse::fromModel(...), $pagination->data),
            count: $pagination->count,
            currentPage: $pagination->currentPage,
            perPage: $pagination->perPage,
            pages: $pagination->pages,
        ));
    }

    public function view(#[RouteArgument] string $id): ResponseInterface
    {
        $user = $this->userService->getOneById(new Uuid($id, field: 'id'));

        if ($user === null) {
            throw new NotFoundException();
        }

        return $this->responseFactory->ok(UserResponse::fromModel($user));
    }

    public function ban(#[RouteArgument] string $id, BanRequest $input): ResponseInterface
    {
        if (!$this->userService->ban(new Uuid($id, field: 'id'), $input->reason())) {
            throw new NotFoundException();
        }

        return $this->responseFactory->noContent();
    }

    public function unban(#[RouteArgument] string $id): ResponseInterface
    {
        if (!$this->userService->unban(new Uuid($id, field: 'id'))) {
            throw new NotFoundException();
        }

        return $this->responseFactory->noContent();
    }
}
