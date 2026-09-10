<?php

declare(strict_types=1);

use Common\Infra\Config\Config;
use Psr\Log\LogLevel;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

$root = dirname(__DIR__, 2);

require_once $root . '/vendor/autoload.php';

if (empty($_ENV['ENVIRONMENT']) && class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

$config = new Config();

if (PHP_SAPI === 'cli-server') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url(is_string($requestUri) ? $requestUri : '', PHP_URL_PATH);

    if (is_string($path) && $path !== '/openapi.yml' && is_file(__DIR__ . $path)) {
        return false;
    }

    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

$runner = new HttpApplicationRunner(
    rootPath: $root,
    debug: $config->isDev(),
    checkEvents: $config->isDev(),
    environment: $config->environment()->value,
    diGroup: 'di-admin',
    paramsGroup: 'params-admin',
    configDirectory: 'common/config',
    temporaryErrorHandler: new ErrorHandler(
        new Logger(
            [
                (new StreamTarget())->setLevels([
                    LogLevel::EMERGENCY,
                    LogLevel::ERROR,
                    LogLevel::WARNING,
                ]),
            ],
        ),
        new HtmlRenderer(),
    ),
);
$runner->run();
