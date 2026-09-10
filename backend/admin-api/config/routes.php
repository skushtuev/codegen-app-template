<?php

declare(strict_types=1);

use AdminApi\Controller\Account\Controller as AccountController;
use AdminApi\Controller\AdminMedia\Controller as AdminMediaController;
use AdminApi\Controller\AdminUsers\Controller as AdminUsersController;
use AdminApi\Controller\Auth\Controller as AuthController;
use AdminApi\Controller\DocsController;
use AdminApi\Middleware\Authenticate;
use AdminApi\Middleware\Can;
use AdminApi\Middleware\MustAuthenticated;
use AdminApi\Middleware\MustNotAuthenticated;
use AdminApi\Middleware\MustNotProduction;
use AdminApi\Service\AdminAccess;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Yii\RateLimiter\LimitRequestsMiddleware;

const ROUTE_ID = '{id:[\w\-]+}';

return [
    Group::create('')->middleware(Authenticate::class)
        ->routes(
            Group::create('/auth')->routes(
                // LimitRequestsMiddleware is outermost: brute force is counted before the user is looked up.
                Route::post('/login')
                    ->middleware(LimitRequestsMiddleware::class, MustNotAuthenticated::class)
                    ->action([AuthController::class, 'login']),
                Route::post('/logout')->middleware(MustAuthenticated::class)->action([AuthController::class, 'logout']),
                Route::post('/whoami')->middleware(MustAuthenticated::class)->action([AuthController::class, 'whoami']),
            ),
            Group::create('/account')->middleware(Authenticate::class, MustAuthenticated::class)->routes(
                Route::post('')->action([AccountController::class, 'account']),
                Route::post('/password')->action([AccountController::class, 'password']),
            ),

            Group::create('/admin-users')->middleware(MustAuthenticated::class, Can::withPermission(AdminAccess::ADMIN_USERS))->routes(
                Route::post('/create')->action([AdminUsersController::class, 'create']),
                Route::post('/ban/' . ROUTE_ID)->action([AdminUsersController::class, 'ban']),
                Route::post('/unban/' . ROUTE_ID)->action([AdminUsersController::class, 'unban']),
                Route::post('/password/' . ROUTE_ID)->action([AdminUsersController::class, 'password']),
                Route::post('/role/' . ROUTE_ID)->action([AdminUsersController::class, 'role']),
                Route::get('/list')->action([AdminUsersController::class, 'list']),
                Route::get('/roles')->action([AdminUsersController::class, 'roles']),
            ),
            Group::create('/admin/media')->middleware(MustAuthenticated::class, Can::withPermission(AdminAccess::ADMIN_MEDIA))->routes(
                Route::get('/config')->action([AdminMediaController::class, 'config']),
                Route::get('/folder-tree')->action([AdminMediaController::class, 'folderTree']),
                Group::create('/folders')->routes(
                    Route::get('')->action([AdminMediaController::class, 'folders']),
                    Route::post('')->action([AdminMediaController::class, 'createFolder']),
                    Route::patch('/' . ROUTE_ID)->action([AdminMediaController::class, 'changeFolder']),
                    Route::delete('/' . ROUTE_ID)->action([AdminMediaController::class, 'deleteFolder']),
                ),
                Group::create('/files')->routes(
                    Route::post('')->action([AdminMediaController::class, 'createFile']),
                    Route::patch('/' . ROUTE_ID)->action([AdminMediaController::class, 'changeFile']),
                    Route::delete('/' . ROUTE_ID)->action([AdminMediaController::class, 'deleteFile']),
                ),
            ),
            Route::get('/uploads/' . ROUTE_ID)->action([AdminMediaController::class, 'upload']),
            Group::create('/docs')->middleware(MustNotProduction::class)->routes(
                Route::get('')->action([DocsController::class, 'index']),
                Route::get('/openapi.yml')->action([DocsController::class, 'openapi']),
            )
        ),
];
