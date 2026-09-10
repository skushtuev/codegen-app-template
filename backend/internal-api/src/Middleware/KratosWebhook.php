<?php

declare(strict_types=1);

namespace InternalApi\Middleware;

use Common\Infra\Config\Config;
use Common\Shared\Http\Exception\NotAuthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Guards the internal Kratos webhook routes with a shared key. No user session involved. */
final readonly class KratosWebhook implements MiddlewareInterface
{
    private const HEADER = 'X-Kratos-Webhook-Key';

    public function __construct(
        private Config $config,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $given = $request->getHeaderLine(self::HEADER);

        // hash_equals: constant time, so the key cannot be guessed byte by byte.
        if ($given === '' || !hash_equals($this->config->mustString('KRATOS_WEBHOOK_KEY'), $given)) {
            throw new NotAuthorizedException();
        }

        return $handler->handle($request);
    }
}
