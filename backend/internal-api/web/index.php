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

$runner = new HttpApplicationRunner(
    rootPath: $root,
    debug: $config->isDev(),
    checkEvents: $config->isDev(),
    environment: $config->environment()->value,
    configDirectory: 'common/config',
    diGroup: 'di-internal',
    paramsGroup: 'params-internal',
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
