<?php

declare(strict_types=1);

namespace AdminApi\Controller\Users\Request;

use Common\Shared\Http\Rule\Integer;
use Common\Shared\Http\Rule\Required;
use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromQuery;

#[FromQuery]
final class ListRequest extends AbstractInput
{
    public function __construct(
        #[Required]
        #[Integer(min: 1)]
        private readonly mixed $page,
        #[Required]
        #[Integer(min: 1, max: 100)]
        private readonly mixed $perPage,
    ) {}

    public function page(): int
    {
        return (int) $this->page;
    }

    public function perPage(): int
    {
        return (int) $this->perPage;
    }
}
