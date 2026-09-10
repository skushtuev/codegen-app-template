<?php

declare(strict_types=1);

namespace InternalApi\Controller\Kratos\Request;

use Common\Shared\Http\Rule\Required;
use Common\Shared\Http\Rule\StringValue;
use Common\Shared\ValueObject\Uuid;
use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;

#[FromBody]
final class IdentityRequest extends AbstractInput
{
    public function __construct(
        #[Required]
        #[StringValue]
        private readonly mixed $identityId,
    ) {}

    public function identityId(): Uuid
    {
        return new Uuid((string) $this->identityId, field: 'identityId');
    }
}
