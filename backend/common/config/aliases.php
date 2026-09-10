<?php

declare(strict_types=1);

return [
    '@root' => dirname(__DIR__, 2),
    '@adminApi' => '@root/admin-api',
    '@internalApi' => '@root/internal-api',
    '@common' => '@root/common',
    '@console' => '@root/console',
    '@public' => '@adminApi/web',
    '@runtime' => '@root/runtime',
    '@vendor' => '@root/vendor',
    '@baseUrl' => '/',
];
