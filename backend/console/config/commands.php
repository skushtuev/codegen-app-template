<?php

declare(strict_types=1);

use Console\Commands\AdminUser\CreateCommand;
use Console\Commands\Kratos\IdentitiesCommand;
use Console\Commands\User\SyncCommand;

return [
    'admin-users:create' => CreateCommand::class,
    'kratos:identities' => IdentitiesCommand::class,
    'users:sync' => SyncCommand::class,
];
