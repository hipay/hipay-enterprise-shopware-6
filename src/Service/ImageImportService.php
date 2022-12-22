<?php

namespace HiPay\Payment\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ImageImportService
{
    private string $mediaRootDirectory;

    protected EntityRepositoryInterface $mediaRepository;

    protected MediaService $mediaService;

    protected FileSaver $fileSaver;

    protected LoggerInterface $logger;

    public function __construct(
        EntityRepositoryInterface $mediaRepository,
        MediaService $mediaService,
        FileSaver $fileSaver,
        string $mediaRootDirectory,
        LoggerInterface $hipayApiLogger
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->mediaService = $mediaService;
        $this->fileSaver = $fileSaver;
        $this->mediaRootDirectory = $mediaRootDirectory;
        $this->logger = $hipayApiLogger;
    }

    public function addImageToMediaFromFile(string $fileName, string $directoryName, string $mediaFolder, Context $context): ?string
    {
        // compose the path to file
        $filePath = dirname(__DIR__).$this->mediaRootDirectory.$directoryName.'/'.$fileName;

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
            $mediaFile = new MediaFile($filePath, $mimeType, $fileExtension, $fileSize);
            $mediaId = $this->mediaService->createMediaInFolder($folder, $context, false);
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $fileName,
                $mediaId,
                $context
            );
        } catch (DuplicatedMediaFileNameException $e) {
            $this->logger->error($e->getCode().' : '.$e->getMessage());
            $mediaId = $this->mediaCleanup($mediaId, $context);
        } catch (FileException $e) {
            $this->logger->error($e->getCode().' : '.$e->getMessage());
            $mediaId = $this->mediaCleanup($mediaId, $context);
        } catch (\Exception $e) {
            $this->logger->error($e->getCode().' : '.$e->getMessage());
            $mediaId = $this->mediaCleanup($mediaId, $context);
        }

        return $mediaId;
    }

    private function mediaCleanup(?string $mediaId, Context $context): mixed
    {
        if ($mediaId) {
            $this->mediaRepository->delete([['id' => $mediaId]], $context);
        }

        return null;
    }
}
