<?php

declare(strict_types=1);

namespace Common\Infra\Kratos\Dto;

use Common\Infra\Kratos\Exception\KratosException;
use Common\Shared\Enum\AppLanguage;
use Common\Shared\ValueObject\Email;
use Common\Shared\ValueObject\NullableText;
use Common\Shared\ValueObject\Uuid;
use Ory\Kratos\Client\Model\Identity;
use stdClass;

/** A Kratos identity mapped to our own types. Keeps the SDK out of services. */
final readonly class KratosIdentity
{
    public function __construct(
        public Uuid $id,
        public Email $email,
        public NullableText $name,
        public AppLanguage $language,
        public bool $active,
        public bool $emailVerified,
    ) {}

    public static function fromSdk(Identity $identity): self
    {
        $traits = $identity->getTraits();
        $email = self::trait($traits, 'email');

        if ($email === null) {
            throw new KratosException(sprintf('Identity "%s" has no email trait.', $identity->getId()));
        }

        return new self(
            id: new Uuid($identity->getId()),
            email: new Email($email),
            name: new NullableText(self::trait($traits, 'name')),
            language: AppLanguage::tryFrom(self::trait($traits, 'language') ?? '') ?? AppLanguage::En,
            active: $identity->getState() === Identity::STATE_ACTIVE,
            emailVerified: self::isVerified($identity, $email),
        );
    }

    /** Traits come back as untyped JSON, so read them defensively. */
    private static function trait(mixed $traits, string $key): ?string
    {
        $value = match (true) {
            is_array($traits) => $traits[$key] ?? null,
            $traits instanceof stdClass => $traits->{$key} ?? null,
            default => null,
        };

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function isVerified(Identity $identity, string $email): bool
    {
        foreach ($identity->getVerifiableAddresses() ?? [] as $address) {
            if ($address->getValue() === $email && $address->getVerified() === true) {
                return true;
            }
        }

        return false;
    }
}
