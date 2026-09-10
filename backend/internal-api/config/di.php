<?php

declare(strict_types=1);

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
use InternalApi\Middleware\NotFoundHandler;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
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

class_alias(Hydrator::class, 'RequestInputHydrator');

return [
    RequestFactoryInterface::class => RequestFactory::class,
    ServerRequestFactoryInterface::class => ServerRequestFactory::class,
    ResponseFactoryInterface::class => ResponseFactory::class,
    DataResponseFactoryInterface::class => JsonResponseFactory::class,
    StreamFactoryInterface::class => StreamFactory::class,
    UriFactoryInterface::class => UriFactory::class,
    UploadedFileFactoryInterface::class => UploadedFileFactory::class,

    // No Authenticate middleware: callers are other containers, they present a shared key.
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

    RouteCollectionInterface::class => static fn(RouteCollectorInterface $collector) => new RouteCollection(
        $collector->addRoute(...(require __DIR__ . '/routes.php')),
    ),
    UrlMatcherInterface::class => static fn(RouteCollectionInterface $routes) => new UrlMatcher($routes, null, null),

    ExceptionResponder::class => static function (
        ResponseFactoryInterface $responseFactory,
        Injector $injector
    ) {
        $logAndFail = static function (
            \Throwable $exception,
            ResponseFactoryInterface $responseFactory,
            LoggerInterface $logger,
        ): ResponseInterface {
            // No stack trace: PHP puts call arguments in it, so it can carry secrets.
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
                $translator = Translator::fromRequest($aliases->getArray(['@common/config/i18n.php']), $request);

                return $jsonResponseFactory->createResponse(
                    ['errors' => $translator->translateResult($exception->getResult())],
                    400,
                );
            },
            ValidationException::class => static function (
                ValidationException $exception,
                JsonResponseFactory $jsonResponseFactory,
                ServerRequestInterface $request,
                Aliases $aliases,
            ): ResponseInterface {
                $translator = Translator::fromRequest($aliases->getArray(['@common/config/i18n.php']), $request);

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
