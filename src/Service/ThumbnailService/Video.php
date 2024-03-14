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
use Pimcore\Tool\Storage;
use Symfony\Component\HttpFoundation\Request;

class Video implements ServiceInterface
{
    use JsonHelperTrait;

    public function async(int $id): void
    {
    }

    public function asyncByRequest(int $id, Request $request): void
    {
        \Pimcore::getContainer()->get('messenger.bus.pimcore-core')->dispatch(
            new AssetThumbnailMessage($id, $request)
        );
    }

    /**
     * @throws FilesystemException
     */
    public function getThumbnail(Request $request): array
    {
        $video = null;
        if ($request->get('id')) {
            $video = Asset\Video::getById((int)$request->get('id'));
        } elseif ($request->get('path')) {
            $video = Asset\Video::getByPath($request->get('path'));
        }
        if ($video && $video->isAllowed('view')) {
            $thumbnail = $this->getThumbnailConfig($video, $request);
            if ($request->get('origin') === 'treeNode' && !$thumbnail->exists()) {
                $this->asyncByRequest($video->getId(), $request);
            }

            $storagePath = $this->getStoragePath($thumbnail,
                $video->getId(),
                $video->getFilename(),
                $video->getRealPath(),
                $video->getChecksum(),
                $video->getDuration()
            );

            $storage = Storage::get('thumbnail');
            if(!$storage->fileExists($storagePath)) {
                $this->asyncByRequest($video->getId(), $request);
            } else {
                return [
                    'path' => $storagePath,
                    'mimeType' => $storage->mimeType($storagePath),
                ];
            }
        }

        return [];
    }

    public function getStoragePath(Asset\Thumbnail\ThumbnailInterface $thumb, int $id, string $filename, string $realPlace, string $checksum, float|int|null $duration): string
    {
        $thumbnail = $thumb->getConfig();
        $timeOffset = ceil($duration / 3);

        $config = $thumb->getConfig();
        $config->setFilenameSuffix('time-' . $timeOffset);
        $format = strtolower($config->getFormat());
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

    public function getThumbnailConfig(Asset $video, Request $request): Asset\Thumbnail\ThumbnailInterface
    {
        $thumbnail = array_merge($request->request->all(), $request->query->all());

        if ($request->get('treepreview')) {
            $thumbnail = Asset\Image\Thumbnail\Config::getPreviewConfig();
        }

        $time = null;
        if (is_numeric($request->get('time'))) {
            $time = (int)$request->get('time');
        }

        if ($request->get('settime')) {
            $video->removeCustomSetting('image_thumbnail_asset');
            $video->setCustomSetting('image_thumbnail_time', $time);
            $video->save();
        }

        $image = null;
        if ($request->get('image')) {
            $image = Asset\Image::getById((int)$request->get('image'));
        }

        if ($request->get('setimage') && $image) {
            $video->removeCustomSetting('image_thumbnail_time');
            $video->setCustomSetting('image_thumbnail_asset', $image->getId());
            $video->save();
        }

        return $video->getImageThumbnail($thumbnail, $time, $image);
    }
}
