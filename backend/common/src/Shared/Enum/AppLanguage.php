<?php

declare(strict_types=1);

namespace Common\Shared\Enum;

/**
 * Languages the app speaks.
 *
 * Mirrors the `language` trait in `kratos/identity/user.schema.json`.
 * A new case must be added there and in `kratos/courier-templates/**` too.
 */
enum AppLanguage: string
{
    case Ru = 'ru';
    case En = 'en';
}
