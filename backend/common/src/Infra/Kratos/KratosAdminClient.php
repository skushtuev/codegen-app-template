<?php

declare(strict_types=1);

namespace Common\Infra\Kratos;

use Common\Infra\Config\Config;
use Common\Infra\Kratos\Dto\KratosIdentity;
use Common\Infra\Kratos\Exception\KratosException;
use Common\Shared\ValueObject\Uuid;
use GuzzleHttp\Client as GuzzleClient;
use Ory\Kratos\Client\Api\IdentityApi;
use Ory\Kratos\Client\ApiException;
use Ory\Kratos\Client\Configuration;
use Ory\Kratos\Client\Model\Identity;
use Ory\Kratos\Client\Model\JsonPatch;
use Throwable;

/**
 * Kratos Admin API (port 4434). Never routed through traefik: it has no auth of its own,
 * so reaching it means being able to change any identity.
 */
final readonly class KratosAdminClient
{
    private IdentityApi $api;

    public function __construct(Config $config)
    {
        $this->api = new IdentityApi(
            new GuzzleClient(),
            Configuration::getDefaultConfiguration()
                ->setHost(rtrim($config->mustString('KRATOS_ADMIN_URL'), '/')),
        );
    }

    public function getIdentity(Uuid $id): ?KratosIdentity
    {
        try {
            $identity = $this->api->getIdentity($id->value());
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw KratosException::from($e);
        } catch (Throwable $e) {
            throw KratosException::from($e);
        }

        return KratosIdentity::fromSdk(self::mustIdentity($identity));
    }

    /** @return KratosIdentity[] */
    public function listIdentities(int $page, int $perPage): array
    {
        try {
            $result = $this->api->listIdentities(perPage: $perPage, page: $page);
        } catch (Throwable $e) {
            throw KratosException::from($e);
        }

        if (!is_array($result)) {
            throw new KratosException('Kratos returned an error instead of an identity list.');
        }

        return array_map(KratosIdentity::fromSdk(...), $result);
    }

    /**
     * The SDK types every call as `Model|ErrorGeneric`, but a non-2xx throws ApiException,
     * so anything that is not the model here means an unexpected response.
     */
    private static function mustIdentity(mixed $value): Identity
    {
        if (!$value instanceof Identity) {
            throw new KratosException('Kratos returned an error instead of an identity.');
        }

        return $value;
    }

    /** Inactive identities cannot log in and their sessions stop working. */
    public function setActive(Uuid $id, bool $active): void
    {
        $patch = new JsonPatch([
            'op' => 'replace',
            'path' => '/state',
            'value' => $active ? Identity::STATE_ACTIVE : Identity::STATE_INACTIVE,
        ]);

        try {
            $this->api->patchIdentity($id->value(), [$patch]);
        } catch (Throwable $e) {
            throw KratosException::from($e);
        }
    }

    public function deleteIdentity(Uuid $id): void
    {
        try {
            $this->api->deleteIdentity($id->value());
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return;
            }
            throw KratosException::from($e);
        } catch (Throwable $e) {
            throw KratosException::from($e);
        }
    }
}
