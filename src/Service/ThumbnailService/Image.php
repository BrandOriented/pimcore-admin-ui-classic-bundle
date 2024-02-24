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
use Pimcore\Messenger\AssetPreviewImageMessage;
use Pimcore\Model\Asset;
use Pimcore\Tool\Storage;
use Symfony\Component\HttpFoundation\Request;

class Image implements ServiceInterface
{
    use JsonHelperTrait;

    public function async(int $id): void
    {
        \Pimcore::getContainer()->get('messenger.bus.pimcore-core')->dispatch(
            new AssetPreviewImageMessage($id)
        );
    }

    /**
     * @throws FilesystemException
     */
    public function getThumbnail(Request $request): array
    {
        $image = Asset\Image::getById((int)$request->get('id'));
        if ($image && $image->isAllowed('view')) {
            $thumbnail = $this->getThumbnailConfig($image, $request);
            if ($request->get('origin') === 'treeNode' && !$thumbnail->exists()) {
                $this->async($image->getId());
            }
            if($request->get('fileinfo')) {
                return [
                    'width' => $thumbnail->getWidth(),
                    'height' => $thumbnail->getHeight(),
                ];
            }

            $storagePath = $this->getStoragePath($thumbnail,
                $image->getId(),
                $image->getFilename(),
                $image->getRealPath(),
                $image->getChecksum()
            );

            $storage = Storage::get('thumbnail');
            if(!$storage->fileExists($storagePath)) {
                $this->async($image->getId());
            }

            return [
                'path' => $storagePath,
                'mimeType' => $thumbnail->getMimeType(),
            ];
        }

        return [];
    }

    public function getStoragePath(Asset\Thumbnail\ThumbnailInterface $thumb, int $id, string $filename, string $realPlace, string $checksum): string
    {
        $thumbnail = $thumb->getConfig();
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

        $thumbDir = rtrim($realPlace, '/').'/'.$id.'/image-thumb__'.$id.'__'. $thumbnail->getName();
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

    public function getThumbnailConfig(Asset $image, Request $request): Asset\Image\ThumbnailInterface
    {
        $thumbnailConfig = null;
        if ($request->get('thumbnail')) {
            $thumbnailConfig = $image->getThumbnail($request->get('thumbnail'))->getConfig() ?? null;
        }
        if (!$thumbnailConfig) {
            if ($request->get('config')) {
                $thumbnailConfig = $image->getThumbnail($this->decodeJson($request->get('config')))->getConfig();
            } else {
                $thumbnailConfig = $image->getThumbnail(array_merge($request->request->all(), $request->query->all()))->getConfig();
            }
        } else {
            // no high-res images in admin mode (editmode)
            // this is mostly because of the document's image editable, which doesn't know anything about the thumbnail
            // configuration, so the dimensions would be incorrect (double the size)
            $thumbnailConfig->setHighResolution(1);
        }

        $format = strtolower($thumbnailConfig->getFormat());
        if ($format == 'source' || $format == 'print') {
            $thumbnailConfig->setFormat('PNG');
            $thumbnailConfig->setRasterizeSVG(true);
        }

        if ($request->query->has('treepreview')) {
            $thumbnailConfig = Asset\Image\Thumbnail\Config::getPreviewConfig();
            if ($request->get('origin') === 'treeNode' && !$image->getThumbnail($thumbnailConfig)->exists()) {
                $this->async($image->getId());

                throw $this->createNotFoundException(sprintf('Tree preview thumbnail not available for asset %s', $image->getId()));
            }
        }

        $cropPercent = $request->query->getBoolean('cropPercent', $request->request->getBoolean('cropPercent'));
        if ($cropPercent) {
            $thumbnailConfig->addItemAt(0, 'cropPercent', [
                'width' => $request->get('cropWidth'),
                'height' => $request->get('cropHeight'),
                'y' => $request->get('cropTop'),
                'x' => $request->get('cropLeft'),
            ]);

            $thumbnailConfig->generateAutoName();
        }

        return $image->getThumbnail($thumbnailConfig);
    }
}
