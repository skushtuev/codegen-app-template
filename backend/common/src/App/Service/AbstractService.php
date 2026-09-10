<?php

declare(strict_types=1);

namespace Common\App\Service;

use Common\Shared\Http\Exception\InternalException;
use Psr\Log\LoggerInterface;
use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

abstract readonly class AbstractService
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    /**
     * Handle exception for API (log and throw exception)
     *
     * @throws InternalException
     */
    protected function handleExceptionForApi(Throwable $e, ?string $method = null): never
    {
        $this->logger->error(
            $e->getMessage(),
            [
                'message' => $e->getMessage(),
                'trace' => $this->sanitizeTrace($e->getTrace()),
                'method' => $method ?: $this->getLastCallerFromBackTrace(),
            ],
        );

        throw new InternalException();
    }

    /**
     * Get db
     */
    protected function getDb(): ConnectionInterface
    {
        return ConnectionProvider::get();
    }

    /**
     * Run operation inside a database transaction (commit on success, roll back and rethrow on failure)
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    protected function transaction(callable $operation): mixed
    {
        $transaction = $this->getDb()->beginTransaction();

        try {
            $result = $operation();
            $transaction->commit();

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Sanitize an exception backtrace by stripping potentially sensitive frame
     * fields (`args`, `object`, ...). Only structural metadata is kept so the
     * trace remains useful for debugging without ever leaking private keys,
     * passwords or other secret values that may have been passed as function
     * arguments.
     *
     * Reference: security audit C-Z1-2 (2026-05-18).
     *
     * @param array<int, array<string, mixed>> $trace
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeTrace(array $trace): array
    {
        $allowedKeys = ['file', 'line', 'function', 'class', 'type'];
        $whitelist = array_flip($allowedKeys);

        return array_map(
            static fn(array $frame): array => array_intersect_key($frame, $whitelist),
            $trace,
        );
    }

    /**
     * Get last caller from backtrace
     */
    private function getLastCallerFromBackTrace(): ?string
    {
        // `DEBUG_BACKTRACE_IGNORE_ARGS` keeps the frame metadata while
        // discarding any function-call argument values — see C-Z1-2.
        foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
            // `class` is optional for top-level / closure frames; bail out as
            // soon as we hit a frame without it. `function` is always set by
            // PHP's backtrace, so we don't check for it explicitly.
            if (!isset($trace['class'])) {
                return null;
            }

            if ($trace['class'] === self::class) {
                continue;
            }

            return $trace['class'] . '::' . $trace['function'];
        }

        return null;
    }
}
