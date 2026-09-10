<?php

declare(strict_types=1);

use InternalApi\Controller\Kratos\Controller as KratosController;
use InternalApi\Middleware\KratosWebhook;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/kratos')->middleware(KratosWebhook::class)->routes(
        Route::post('/sync')->action([KratosController::class, 'sync']),
        Route::post('/after-login')->action([KratosController::class, 'afterLogin']),
    ),
];
