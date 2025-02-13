<?php

namespace HiPay\Payment\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ImageImportService
{
    protected LoggerInterface $logger;

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
     */
    public function __construct(
        protected EntityRepository $mediaRepository,
        protected EntityRepository $mediaFolderRepository,
        protected MediaService $mediaService,
        protected FileSaver $fileSaver,
        private string $mediaRootDirectory,
        LoggerInterface $hipayApiLogger
    ) {
        $this->logger = $hipayApiLogger;
    }

    public function addImageToMediaFromFile(string $fileName, string $directoryName, string $mediaFolder, Context $context): ?string
    {
        // compose the path to file
        $filePath = dirname(__DIR__) . $this->mediaRootDirectory . $directoryName . '/' . $fileName;

        // get the file extension
        $fileNameParts = explode('.', $fileName);
        $fileName = $fileNameParts[0];
        $fileExtension = $fileNameParts[1];

        // create media record from the image and return its ID
        return $this->createMediaFromFile($filePath, $fileName, $fileExtension, $mediaFolder, $context);
    }

    private function createMediaFromFile(string $filePath, string $fileName, string $fileExtension, string $folder, Context $context): ?string
    {
        $mediaId = null;

        // get additional info on the file
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath);

        if (!$fileSize) {
            throw new FileException("Invalid filesize on $filePath");
        }
        if (!$mimeType) {
            throw new FileException("Invalid mime type on $filePath");
        }

        // create and save new media file to the Shopware's media library
        try {
            if (!$mediaId = $this->isMediaExisting($fileName, $fileExtension, $folder, $context)) {
                $mediaFile = new MediaFile($filePath, $mimeType, $fileExtension, $fileSize);
                $mediaId = $this->mediaService->saveMediaFile($mediaFile, $fileName, $context, $folder, null, false);
            }
        } catch (MediaException $e) {
            $this->logger->error($e->getCode() . ' : ' . $e->getMessage());
            $mediaId = $this->mediaCleanup($mediaId, $context);
        } catch (FileException $e) {
            $this->logger->error($e->getCode() . ' : ' . $e->getMessage());
            $mediaId = $this->mediaCleanup($mediaId, $context);
        } catch (\Exception $e) {
            $this->logger->error($e->getCode() . ' : ' . $e->getMessage());
            $mediaId = $this->mediaCleanup($mediaId, $context);
        }

        return $mediaId;
    }

    private function isMediaExisting(string $fileName, string $fileExtension, string $folder, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaFolderId', $this->getMediaDefaultFolderId($folder, $context)));
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));
        $criteria->addFilter(new EqualsFilter('fileExtension', $fileExtension));
        $criteria->setLimit(1);

        return $this->mediaRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * Delete media on database.
     *
     * @return null
     */
    private function mediaCleanup(?string $mediaId, Context $context)
    {
        if ($mediaId) {
            $this->mediaRepository->delete([['id' => $mediaId]], $context);
        }

        return null;
    }

    private function getMediaDefaultFolderId(string $folder, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $folder));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $context);
        $defaultFolderId = null;
        if ($defaultFolder->count() === 1) {
            $folder = $defaultFolder->getEntities()->first();
            if (!is_null($folder) && method_exists($folder, 'getId')) {
                $defaultFolderId = $folder->getId();
            }
        }

        return $defaultFolderId;
    }
}
