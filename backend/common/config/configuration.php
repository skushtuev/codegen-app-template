<?php

declare(strict_types=1);

// NOTE: After making changes in this file, run `composer yii-config-rebuild` to update the merge plan.
return [
    'config-plugin' => [
        'params' => 'params.php',
        // `params-web` / `di-web` are the shared base: vendor packages register into them.
        // Each HTTP app adds its own file on top and runs with its own group.
        'params-web' => '$params',
        'params-admin' => [
            '$params-web',
            '../../admin-api/config/params.php',
        ],
        'params-internal' => [
            '$params-web',
            '../../internal-api/config/params.php',
        ],
        'params-console' => [
            '$params',
            '../../console/config/params.php',
        ],
        'di' => 'di.php',
        'di-web' => '$di',
        'di-admin' => [
            '$di-web',
            '../../admin-api/config/di.php',
        ],
        'di-internal' => [
            '$di-web',
            '../../internal-api/config/di.php',
        ],
        'di-console' => '$di',
        'di-delegates' => [],
        'di-delegates-console' => '$di-delegates',
        'di-delegates-web' => '$di-delegates',
        'di-providers' => [],
        'di-providers-web' => '$di-providers',
        'di-providers-console' => '$di-providers',
        'events' => [],
        'events-web' => '$events',
        'events-console' => '$events',
        'routes' => [],
        'bootstrap' => 'bootstrap.php',
        'bootstrap-web' => '$bootstrap',
        'bootstrap-console' => '$bootstrap',
    ],
    'config-plugin-environments' => [
        'dev' => [
            'params' => 'environments/dev/params.php',
        ],
        'prod' => [
            'params' => 'environments/prod/params.php',
        ],
        'test' => [
            'params' => 'environments/test/params.php',
        ],
    ],
    'config-plugin-options' => [
        'source-directory' => 'common/config',
    ],
];
