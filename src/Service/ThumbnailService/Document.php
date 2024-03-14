<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\AdminBundle\Service\ThumbnailService;

use League\Flysystem\FilesystemException;
use Pimcore\Bundle\AdminBundle\Service\ThumbnailService;
use Pimcore\Controller\Traits\JsonHelperTrait;
use Pimcore\Messenger\AssetThumbnailMessage;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Document\ImageThumbnailInterface;
use Pimcore\Tool\Storage;
use Symfony\Component\HttpFoundation\Request;

class Document implements ServiceInterface
{
    use JsonHelperTrait;

    public function async(int $id): void
    {
    }

    public function asyncByRequest(int $id, Request $request): void
    {
        \Pimcore::getContainer()->get('messenger.bus.pimcore-core')->dispatch(
            new AssetThumbnailMessage($id, array_merge($request->request->all(), $request->query->all()))
        );
    }

    /**
     * @throws FilesystemException
     */
    public function getThumbnail(Request $request): array
    {
        $document = Asset\Document::getById((int)$request->get('id'));
        if ($document && $document->isAllowed('view')) {
            $thumbnail = $this->getThumbnailConfig($document, $request);
            $page = 1;
            if (is_numeric($request->get('page'))) {
                $page = (int)$request->get('page');
            }

            if ($request->get('origin') === 'treeNode' && !$thumbnail->exists()) {
                $this->asyncByRequest($document->getId(), $request);
            }

            $storagePath = $this->getStoragePath($thumbnail,
                $page,
                $document->getId(),
                $document->getFilename(),
                $document->getRealPath(),
                $document->getChecksum()
            );
            $storage = Storage::get('thumbnail');
            if(!$storage->fileExists($storagePath)) {
                $this->asyncByRequest($document->getId(), $request);
            } else {
                return [
                    'path' => $storagePath,
                    'mimeType' => $storage->mimeType($storagePath),
                ];
            }
        }

        return [];
    }

    public function getStoragePath(Asset\Thumbnail\ThumbnailInterface $thumb,
        int $page,
        int $id,
        string $filename,
        string $realPath,
        string $checksum): string
    {
        $thumbnail = $thumb->getConfig();
        $thumbnail->setFilenameSuffix('page-' . $page);
        $format = strtolower($thumbnail->getFormat());
        $fileExt = pathinfo($filename, PATHINFO_EXTENSION);

        // simple detection for source type if SOURCE is selected
        if ($format == 'source' || empty($format)) {
            $thumbnail->setFormat('jpeg'); // default format for documents is JPEG not PNG (=too big)
            $optimizedFormat = true;
            $format = ThumbnailService::getAllowedFormat($fileExt, ['pjpeg', 'jpeg', 'gif', 'png'], 'png');
            if ($format === 'jpeg') {
                $format = 'pjpeg';
            }
        }

        $thumbDir = rtrim($realPath, '/').'/'.$id.'/image-thumb__'.$id.'__'. $thumbnail->getName();
        $filename = preg_replace("/\." . preg_quote(pathinfo($filename, PATHINFO_EXTENSION), '/') . '$/i', '', $filename);

        // add custom suffix if available
        if ($thumbnail->getFilenameSuffix()) {
            $filename .= '~-~' . $thumbnail->getFilenameSuffix();
        }
        // add high-resolution modifier suffix to the filename
        if ($thumbnail->getHighResolution() > 1) {
            $filename .= '@' . $thumbnail->getHighResolution() . 'x';
        }

        $fileExtension = $format;
        if ($format == 'original') {
            $fileExtension = $fileExt;
        } elseif ($format === 'pjpeg' || $format === 'jpeg') {
            $fileExtension = 'jpg';
        }

        $filename .= '.' . $thumbnail->getHash([$checksum]) . '.'. $fileExtension;

        return $thumbDir . '/' . $filename;
    }

    public function getThumbnailConfig(Asset $document, Request $request): ImageThumbnailInterface
    {
        $thumbnail = Asset\Image\Thumbnail\Config::getByAutoDetect(array_merge($request->request->all(), $request->query->all()));

        $format = strtolower($thumbnail->getFormat());
        if ($format == 'source') {
            $thumbnail->setFormat('jpeg'); // default format for documents is JPEG not PNG (=too big)
        }

        if ($request->get('treepreview')) {
            $thumbnail = Asset\Image\Thumbnail\Config::getPreviewConfig();
        }

        $page = 1;
        if (is_numeric($request->get('page'))) {
            $page = (int)$request->get('page');
        }

        return $document->getImageThumbnail($thumbnail, $page);
    }
}
