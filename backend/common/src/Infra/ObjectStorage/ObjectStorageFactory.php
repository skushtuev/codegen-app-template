<?php

declare(strict_types=1);

namespace Common\Infra\ObjectStorage;

use Common\Infra\Config\Config;
use Common\Infra\ObjectStorage\Exception\ObjectStorageException;

final readonly class ObjectStorageFactory
{
    private const PROVIDER_NOP = 'nop';
    private const PROVIDER_FILE = 'file';

    public function __construct(
        private Config $config,
    ) {}

    public function create(): ObjectStorageInterface
    {
        $provider = $this->config->string('OBJECT_STORAGE_PROVIDER', self::PROVIDER_NOP);

        return match ($provider) {
            self::PROVIDER_NOP => new NopObjectStorage(),
            self::PROVIDER_FILE => new FileObjectStorage(
                $this->ensureDirectory($this->config->mustString('OBJECT_STORAGE_ROOT_PATH')),
            ),
            default => throw new ObjectStorageException(sprintf('Object storage provider "%s" is not supported.', $provider)),
        };
    }

    /** `runtime/` is gitignored, so on a fresh clone this directory does not exist yet. */
    private function ensureDirectory(string $path): string
    {
        // The second is_dir() covers another process creating it between the two calls.
        if (!is_dir($path) && !mkdir($path, 0o775, true) && !is_dir($path)) {
            throw new ObjectStorageException(sprintf('Object storage root path "%s" could not be created.', $path));
        }

        return $path;
    }
}
