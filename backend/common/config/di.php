<?php

declare(strict_types=1);

use Common\Infra\Cache\CacheFactory;
use Common\Infra\Config\Config;
use Common\Infra\Db\ConnectionFactory;
use Common\Infra\ObjectStorage\ObjectStorageFactory;
use Common\Infra\ObjectStorage\ObjectStorageInterface;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Log\Target\File\FileRotator;
use Yiisoft\Log\Target\File\FileTarget;

/** @var array $params */

return [
    Config::class => Config::class,

    // Errors go to a rotated FILE as well as stdout. Not a preference: under php-fpm the workers' stdout is
    // discarded (catch_workers_output is off), so a 500 raised inside an HTTP request left no trace anywhere —
    // the access log records the status, never the cause. The console keeps stdout, which is where it does work.
    LoggerInterface::class => DynamicReference::to(
        static fn(Aliases $aliases): LoggerInterface => new Logger([
            new StreamTarget(),
            new FileTarget(
                logFile: $aliases->get('@runtime/logs/error.log'),
                // Rotation is the whole reason for the file target over a plain stream: an error log nobody
                // prunes fills the disk on the day it is needed most. 10 MB × 10 files, older ones gzipped.
                rotator: new FileRotator(maxFileSize: 10240, maxFiles: 10, compressRotatedFiles: true),
                levels: [
                    LogLevel::EMERGENCY,
                    LogLevel::ALERT,
                    LogLevel::CRITICAL,
                    LogLevel::ERROR,
                ],
            ),
        ]),
    ),

    ConnectionInterface::class => DynamicReference::to(
        static fn(ConnectionFactory $factory): ConnectionInterface => $factory->create(),
    ),

    CacheInterface::class => DynamicReference::to(
        static fn(CacheFactory $factory): CacheInterface => $factory->create(),
    ),
    PsrCacheInterface::class => DynamicReference::to(
        static fn(CacheFactory $factory): PsrCacheInterface => $factory->createPsr(),
    ),

    ObjectStorageInterface::class => DynamicReference::to(
        static fn(ObjectStorageFactory $factory): ObjectStorageInterface => $factory->create(),
    ),
];
