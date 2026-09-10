<?php

declare(strict_types=1);

use AdminApi\Middleware\NotFoundHandler;
use Common\Shared\Http\LimitPerClientIp;
use Common\Shared\Exception\ValidationException;
use Common\Shared\Http\Exception\ForbiddenException;
use Common\Shared\Http\Exception\InternalException;
use Common\Shared\Http\Exception\NotAuthorizedException;
use Common\Shared\Http\Exception\NotFoundException;
use Common\Shared\Translator;
use HttpSoft\Message\RequestFactory;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\StreamFactory;
use HttpSoft\Message\UploadedFileFactory;
use HttpSoft\Message\UriFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Middleware\ExceptionResponder;
use Yiisoft\Hydrator\Exception\WrongConstructorArgumentsCountException;
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Hydrator\TypeCaster\NoTypeCaster;
use Yiisoft\Hydrator\Validator\ValidatingHydrator;
use Yiisoft\Injector\Injector;
use Yiisoft\Input\Http\HydratorAttributeParametersResolver;
use Yiisoft\Input\Http\InputValidationException;
use Yiisoft\Input\Http\RequestInputParametersResolver;
use Yiisoft\Middleware\Dispatcher\CompositeParametersResolver;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\ParametersResolverInterface;
use Yiisoft\Request\Body\RequestBodyParser;
use Yiisoft\RequestProvider\RequestCatcherMiddleware;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteCollectorInterface;
use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\RateLimiter\Counter;
use Yiisoft\Yii\RateLimiter\CounterInterface;
use Yiisoft\Yii\RateLimiter\LimitRequestsMiddleware;
use Yiisoft\Yii\RateLimiter\Policy\LimitPolicyInterface;
use Yiisoft\Yii\RateLimiter\Storage\SimpleCacheStorage;
use Yiisoft\Yii\RateLimiter\Storage\StorageInterface;

class_alias(Hydrator::class, 'RequestInputHydrator');

return [
    RequestFactoryInterface::class => RequestFactory::class,
    ServerRequestFactoryInterface::class => ServerRequestFactory::class,
    ResponseFactoryInterface::class => ResponseFactory::class,
    DataResponseFactoryInterface::class => JsonResponseFactory::class,
    StreamFactoryInterface::class => StreamFactory::class,
    UriFactoryInterface::class => UriFactory::class,
    UploadedFileFactoryInterface::class => UploadedFileFactory::class,
    Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to([
                'class' => MiddlewareDispatcher::class,
                'withMiddlewares()' => [
                    [
                        ErrorCatcher::class,
                        ExceptionResponder::class,
                        RequestCatcherMiddleware::class,
                        RequestBodyParser::class,
                        Router::class,
                    ],
                ],
            ]),
            'fallbackHandler' => Reference::to(NotFoundHandler::class),
        ],
    ],

    ParametersResolverInterface::class => [
        'class' => CompositeParametersResolver::class,
        '__construct()' => [
            Reference::to(HydratorAttributeParametersResolver::class),
            Reference::to(RequestInputParametersResolver::class),
        ],
    ],
    'RequestInputHydrator' => [
        'class' => 'RequestInputHydrator',
        '__construct()' => [
            'typeCaster' => Reference::to(NoTypeCaster::class),
        ],
    ],
    ValidatingHydrator::class => [
        'class' => ValidatingHydrator::class,
        '__construct()' => [
            'hydrator' => Reference::to('RequestInputHydrator'),
        ],
    ],
    RequestInputParametersResolver::class => [
        'class' => RequestInputParametersResolver::class,
        '__construct()' => [
            'hydrator' => Reference::to(ValidatingHydrator::class),
            'throwInputValidationException' => true,
        ],
    ],

    // Rate limiting (yiisoft/rate-limiter): GCRA counter kept in the shared Redis cache.
    StorageInterface::class => SimpleCacheStorage::class,
    CounterInterface::class => [
        'class' => Counter::class,
        '__construct()' => [
            'limit' => 10,
            'periodInSeconds' => 120,
        ],
    ],
    LimitPolicyInterface::class => LimitPerClientIp::class,
    LimitRequestsMiddleware::class => [
        'class' => LimitRequestsMiddleware::class,
        '__construct()' => [
            'limitingPolicy' => Reference::to(LimitPolicyInterface::class),
        ],
    ],

    RouteCollectionInterface::class => static fn(RouteCollectorInterface $collector) => new RouteCollection(
        $collector->addRoute(...(require __DIR__ . '/routes.php')),
    ),
    UrlMatcherInterface::class => static fn(RouteCollectionInterface $routes) => new UrlMatcher(
        $routes,
        null,
        null,
    ),
    ExceptionResponder::class => static function (
        ResponseFactoryInterface $responseFactory,
        Injector $injector
    ) {
        // Every 500 passes through here. ExceptionResponder sits inside ErrorCatcher, so it catches the
        // exception first and ErrorCatcher never logs it: without this the cause of a 500 is lost and only
        // the access log's status code survives.
        $logAndFail = static function (
            \Throwable $exception,
            ResponseFactoryInterface $responseFactory,
            LoggerInterface $logger,
        ): ResponseInterface {
            // No stack trace on purpose: PHP puts call arguments in it, so a trace can carry passwords or
            // ADMIN_AUTH_JWT_SECRET. AbstractService::handleExceptionForApi() strips them for the same reason.
            $logger->error($exception->getMessage(), [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $responseFactory->createResponse(500);
        };

        $exceptionMap = [
            InputValidationException::class => static function (
                InputValidationException $exception,
                JsonResponseFactory $jsonResponseFactory,
                ServerRequestInterface $request,
                Aliases $aliases,
            ): ResponseInterface {
                $result = $exception->getResult();
                $translator = Translator::fromRequest(
                    $aliases->getArray(['@common/config/i18n.php', '@adminApi/config/i18n.php']),
                    $request,
                );

                return $jsonResponseFactory->createResponse(
                    [
                        'errors' => $translator->translateResult($result),
                    ],
                    400,
                );
            },
            ValidationException::class => static function (
                ValidationException $exception,
                JsonResponseFactory $jsonResponseFactory,
                ServerRequestInterface $request,
                Aliases $aliases,
            ): ResponseInterface {
                $translator = Translator::fromRequest(
                    $aliases->getArray(['@common/config/i18n.php', '@adminApi/config/i18n.php']),
                    $request,
                );

                return $jsonResponseFactory->createResponse(
                    [
                        'errors' => [
                            ($exception->field() ?? 'common') => $translator->translate(
                                $exception->messageKey(),
                                $exception->messageParams(),
                            ),
                        ],
                    ],
                    400,
                );
            },
            WrongConstructorArgumentsCountException::class => 400,
            NotAuthorizedException::class => 401,
            ForbiddenException::class => 403,
            NotFoundException::class => 404,
            InternalException::class => $logAndFail,
            \Throwable::class => $logAndFail,
        ];

        return new ExceptionResponder($exceptionMap, $responseFactory, $injector);
    },
];
