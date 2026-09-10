<?php

declare(strict_types=1);

namespace AdminApi\Controller\Users\Request;

use Common\Shared\Http\Rule\Length;
use Common\Shared\Http\Rule\StringValue;
use Common\Shared\ValueObject\NullableText;
use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;

#[FromBody]
final class BanRequest extends AbstractInput
{
    public function __construct(
        #[StringValue]
        #[Length(max: 500)]
        private readonly mixed $reason = null,
    ) {}

    public function reason(): NullableText
    {
        return new NullableText($this->reason === null ? null : (string) $this->reason);
    }
}
