<?php

declare(strict_types=1);

namespace Common\Infra\Kratos;

use Common\Infra\Config\Config;
use Common\Infra\Kratos\Dto\KratosIdentity;
use Common\Infra\Kratos\Exception\KratosException;
use GuzzleHttp\Client as GuzzleClient;
use Ory\Kratos\Client\Api\FrontendApi;
use Ory\Kratos\Client\ApiException;
use Ory\Kratos\Client\Configuration;
use Ory\Kratos\Client\Model\Session;
use Throwable;

/** Kratos public API (port 4433): turns a browser session cookie into an identity. */
final readonly class KratosSessionClient
{
    private FrontendApi $api;

    public function __construct(Config $config)
    {
        $this->api = new FrontendApi(
            new GuzzleClient(),
            Configuration::getDefaultConfiguration()
                ->setHost(rtrim($config->mustString('KRATOS_PUBLIC_URL'), '/')),
        );
    }

    /** @param string $cookie full Cookie header, e.g. "ory_kratos_session=..." */
    public function whoami(string $cookie): ?KratosIdentity
    {
        try {
            $session = $this->api->toSession(cookie: $cookie);
        } catch (ApiException $e) {
            if ($e->getCode() === 401 || $e->getCode() === 403) {
                return null;
            }
            throw KratosException::from($e);
        } catch (Throwable $e) {
            throw KratosException::from($e);
        }

        if (!$session instanceof Session) {
            throw new KratosException('Kratos returned an error instead of a session.');
        }

        $identity = $session->getIdentity();

        return $identity === null ? null : KratosIdentity::fromSdk($identity);
    }
}
