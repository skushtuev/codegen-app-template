<?php

declare(strict_types=1);

namespace Common\App\Service\AdminMedia;

use Common\App\Models\AdminMediaFile;
use Common\App\Models\AdminMediaFolder;
use Common\App\Repository\AdminMediaFileRepository;
use Common\App\Repository\AdminMediaFolderRepository;
use Common\App\Service\AbstractService;
use Common\App\Service\AdminMedia\Data\ChangeFileDto;
use Common\App\Service\AdminMedia\Data\ChangeFolderDto;
use Common\App\Service\AdminMedia\Data\CreateFileDto;
use Common\App\Service\AdminMedia\Data\CreateFolderDto;
use Common\App\Service\AdminMedia\Data\FolderContentDto;
use Common\App\Service\AdminMedia\Data\FolderTreeNodeDto;
use Common\App\Service\AdminMedia\Data\FolderWithCountersDto;
use Common\Infra\ObjectStorage\Exception\ObjectNotFoundException;
use Common\Infra\ObjectStorage\ObjectStorageInterface;
use Common\Shared\Exception\ValidationException;
use Common\Shared\ValueObject\Text;
use Common\Shared\ValueObject\Uuid;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class Service extends AbstractService
{
    private const STORAGE_PREFIX = 'admin-media';

    public function __construct(
        LoggerInterface $logger,
        private AdminMediaFolderRepository $folderRepo,
        private AdminMediaFileRepository $fileRepo,
        private ObjectStorageInterface $objectStorage,
    ) {
        parent::__construct($logger);
    }

    public function getFolderContent(?Uuid $parentId = null): ?FolderContentDto
    {
        $currentFolder = null;
        $parents = [];

        if ($parentId !== null) {
            if (!$currentFolder = $this->folderRepo->getOneById($parentId)) {
                return null;
            }

            $parents = $this->getParents($currentFolder);
        }

        return new FolderContentDto(
            currentFolder: $currentFolder,
            parents: $parents,
            folders: array_map(
                fn(AdminMediaFolder $folder) => new FolderWithCountersDto(
                    folder: $folder,
                    foldersCount: $this->folderRepo->countByParentId($folder->getId()),
                    filesCount: $this->fileRepo->countByFolderId($folder->getId()),
                ),
                $this->folderRepo->getList($parentId),
            ),
            files: $this->fileRepo->getList($parentId),
        );
    }

    /**
     * @return FolderTreeNodeDto[]
     */
    public function getFolderTree(): array
    {
        $childrenByParentId = [];

        foreach ($this->folderRepo->getListForTree() as $folder) {
            $childrenByParentId[$folder->getParentId()?->value() ?? ''][] = $folder;
        }

        return $this->buildFolderTree(
            parentId: null,
            childrenByParentId: $childrenByParentId,
            visited: [],
        );
    }

    public function getFile(Uuid $id): ?AdminMediaFile
    {
        return $this->fileRepo->getOneById($id);
    }

    /**
     * @return resource|null
     */
    public function getFileResource(AdminMediaFile $file)
    {
        try {
            return $this->objectStorage->get($file->getStorageKey()->value());
        } catch (ObjectNotFoundException) {
            return null;
        }
    }

    public function createFolder(CreateFolderDto $dto): AdminMediaFolder
    {
        if ($dto->parentId !== null && $this->folderRepo->getOneById($dto->parentId) === null) {
            throw new ValidationException(messageKey: 'admin_media.folder_parent_not_found', field: 'parentId');
        }

        if ($this->folderRepo->hasByParentIdAndName($dto->parentId, $dto->name)) {
            throw new ValidationException(messageKey: 'admin_media.folder_name_already_exists', field: 'name');
        }

        $folder = $this->folderRepo->getEmptyModel();
        $folder->setParentId($dto->parentId);
        $folder->setName($dto->name);
        $folder->setCreatedBy($dto->createdBy);

        return $this->folderRepo->save($folder);
    }

    public function changeFolder(ChangeFolderDto $dto): bool
    {
        $folder = $this->folderRepo->getOneById($dto->id);

        if ($folder === null) {
            return false;
        }

        $sameNameExists = $this->folderRepo->hasByParentIdAndName($folder->getParentId(), $dto->name);
        if ($sameNameExists && $folder->getName()->value() !== $dto->name->value()) {
            throw new ValidationException(messageKey: 'admin_media.folder_name_already_exists', field: 'name');
        }

        $folder->setName($dto->name);
        $this->folderRepo->save($folder);

        return true;
    }

    public function createFile(CreateFileDto $dto): AdminMediaFile
    {
        if ($dto->folderId !== null && $this->folderRepo->getOneById($dto->folderId) === null) {
            throw new ValidationException(messageKey: 'admin_media.folder_not_found', field: 'folderId');
        }

        if ($this->fileRepo->hasByFolderIdAndName($dto->folderId, $dto->name)) {
            throw new ValidationException(messageKey: 'admin_media.file_name_already_exists', field: 'name');
        }

        $resource = $dto->file->stream();
        $extension = $dto->file->extension();
        $storageKey = sprintf(
            '%s/%s/%s.%s',
            self::STORAGE_PREFIX,
            date('Y/m'),
            bin2hex(random_bytes(16)),
            $extension,
        );

        $stored = false;
        try {
            $this->objectStorage->put($storageKey, $resource);
            $stored = true;

            $file = $this->fileRepo->getEmptyModel();
            $file->setFolderId($dto->folderId);
            $file->setStorageKey(new Text($storageKey));
            $file->setOriginalName(new Text($dto->file->filename()));
            $file->setName($dto->name);
            $file->setExtension(new Text($extension));
            $file->setMimeType(new Text($dto->file->mimeType()));
            $file->setSize($dto->file->size());
            $file->setChecksum(new Text($dto->file->checksum()));
            $file->setIsPublic($dto->isPublic);
            $file->setCreatedBy($dto->createdBy);

            return $this->fileRepo->save($file);
        } catch (Throwable $exception) {
            if ($stored) {
                try {
                    $this->objectStorage->delete($storageKey);
                } catch (Throwable) {
                }
            }

            throw $exception;
        } finally {
            $dto->file->close();
        }
    }

    public function changeFile(ChangeFileDto $dto): bool
    {
        $file = $this->fileRepo->getOneById($dto->id);

        if ($file === null) {
            return false;
        }

        if ($dto->folderId !== null && $this->folderRepo->getOneById($dto->folderId) === null) {
            throw new ValidationException(messageKey: 'admin_media.folder_not_found', field: 'folderId');
        }

        $sameNameFile = $this->fileRepo->getOneByFolderIdAndName($dto->folderId, $dto->name);
        if ($sameNameFile !== null && $sameNameFile->getId()->value() !== $dto->id->value()) {
            throw new ValidationException(messageKey: 'admin_media.file_name_already_exists', field: 'name');
        }

        $file->setFolderId($dto->folderId);
        $file->setName($dto->name);
        $file->setIsPublic($dto->isPublic);
        $this->fileRepo->save($file);

        return true;
    }

    public function deleteFile(Uuid $id): bool
    {
        $file = $this->fileRepo->getOneById($id);

        if ($file === null) {
            return false;
        }

        if (!$this->canDelete($file)) {
            throw new ValidationException(messageKey: 'admin_media.file_can_not_delete', field: 'id');
        }

        $this->transaction(function () use ($file) {
            $this->fileRepo->delete($file);
            $this->objectStorage->delete($file->getStorageKey()->value());
        });

        return true;
    }

    private function canDelete(AdminMediaFile $file): bool
    {
        // check here dependencies from other services
        return true;
    }

    /**
     * @return AdminMediaFolder[]
     */
    private function getParents(AdminMediaFolder $folder): array
    {
        $parents = [];
        $visited = [
            $folder->getId()->value() => true,
        ];
        $parentId = $folder->getParentId();

        while ($parentId !== null && !isset($visited[$parentId->value()])) {
            $parent = $this->folderRepo->getOneById($parentId);

            if ($parent === null) {
                break;
            }

            $parents[] = $parent;
            $visited[$parent->getId()->value()] = true;
            $parentId = $parent->getParentId();
        }

        return array_reverse($parents);
    }

    /**
     * @param array<string, AdminMediaFolder[]> $childrenByParentId
     * @param array<string, true> $visited
     * @return FolderTreeNodeDto[]
     */
    private function buildFolderTree(?Uuid $parentId, array $childrenByParentId, array $visited): array
    {
        $parentKey = $parentId?->value() ?? '';
        $tree = [];

        foreach ($childrenByParentId[$parentKey] ?? [] as $folder) {
            $folderId = $folder->getId()->value();

            if (isset($visited[$folderId])) {
                continue;
            }

            $tree[] = new FolderTreeNodeDto(
                folder: $folder,
                children: $this->buildFolderTree(
                    parentId: $folder->getId(),
                    childrenByParentId: $childrenByParentId,
                    visited: $visited + [$folderId => true],
                ),
            );
        }

        return $tree;
    }

    public function deleteFolder(Uuid $id): bool
    {
        $folder = $this->folderRepo->getOneById($id);

        if ($folder === null) {
            return false;
        }

        if ($this->folderRepo->hasChildren($id) || $this->fileRepo->hasByFolderId($id)) {
            throw new ValidationException(messageKey: 'admin_media.folder_not_empty', field: 'id');
        }

        $this->folderRepo->delete($folder);

        return true;
    }
}
