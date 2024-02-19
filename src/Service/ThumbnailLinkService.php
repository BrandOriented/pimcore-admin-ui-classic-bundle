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

namespace Pimcore\Bundle\AdminBundle\Service;

use Pimcore\Model\Asset;
use Pimcore\Tool\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ThumbnailLinkService
{
    public static function getVideo(int $id)
    {
        $video = Asset\Video::getById($id);
        $thumbnail = Asset\Image\Thumbnail\Config::getPreviewConfig();
        $timeOffset = 2;
        $thumb = $video->getImageThumbnail($thumbnail);

        $config = $thumb->getConfig();
        $config->setFilenameSuffix('time-' . $timeOffset);
        $format = strtolower($config->getFormat());
        $fileExt = pathinfo($video->getFilename(), PATHINFO_EXTENSION);

        $thumbDir = rtrim($video->getRealPath(), '/').'/'.$video->getId().'/image-thumb__'.$video->getId().'__'. $config->getName();
        $filename = preg_replace("/\." . preg_quote(pathinfo($video->getFilename(), PATHINFO_EXTENSION), '/') . '$/i', '', $video->getFilename());

        // add custom suffix if available
        if ($config->getFilenameSuffix()) {
            $filename .= '~-~' . $config->getFilenameSuffix();
        }
        // add high-resolution modifier suffix to the filename
        if ($config->getHighResolution() > 1) {
            $filename .= '@' . $config->getHighResolution() . 'x';
        }

        $fileExtension = $format;
        if ($format == 'original') {
            $fileExtension = $fileExt;
        } elseif ($format === 'pjpeg' || $format === 'jpeg') {
            $fileExtension = 'jpg';
        }

        $filename .= '.' . $config->getHash([$video->getChecksum()]) . '.'. $fileExtension;
        $storagePath = $thumbDir . '/' . $filename;
        $storage = Storage::get('thumbnail');

        if (!$storage->fileExists($storagePath)) {
            return null;
        }

        return urlencode_ignore_slash($storage->publicUrl($storagePath));
    }

    public static function getFolder(int $id)
    {
        $folder = Asset\Folder::getById($id);
        $storage = Storage::get('thumbnail');
        $cacheFilePath = sprintf(
            '%s/%s/image-thumb__%s__-folder-preview%s.jpg',
            rtrim($folder->getRealPath(), '/'),
            $folder->getId(),
            $folder->getId(),
            '-hdpi'
        );

        if ($folder instanceof  Asset\Folder) {
            if (!$folder->isAllowed('view')) {
                return null;
            }
            if($storage->fileExists($cacheFilePath)) {
                return urlencode_ignore_slash($storage->publicUrl($cacheFilePath));
            }
        }

        return null;
    }

    public static function getImage(int $id)
    {
        $asset = Asset\Image::getById($id);

        if (!$asset) {
            throw new NotFoundHttpException('could not load document asset');
        }

        if (!$asset->isAllowed('view')) {
            return null;
        }

        $thumbnail = Asset\Image\Thumbnail\Config::getPreviewConfig();
        $thumb = $asset->getThumbnail($thumbnail);

        $config = $thumb->getConfig();
        $format = strtolower($config->getFormat());
        $fileExt = pathinfo($asset->getFilename(), PATHINFO_EXTENSION);

        // simple detection for source type if SOURCE is selected
        if ($format == 'source' || empty($format)) {
            $thumbnail->setFormat('jpeg'); // default format for documents is JPEG not PNG (=too big)
            $optimizedFormat = true;
            $format = self::getAllowedFormat($fileExt, ['pjpeg', 'jpeg', 'gif', 'png'], 'png');
            if ($format === 'jpeg') {
                $format = 'pjpeg';
            }
        }

        $thumbDir = rtrim($asset->getRealPath(), '/').'/'.$asset->getId().'/image-thumb__'.$asset->getId().'__'. $config->getName();
        $filename = preg_replace("/\." . preg_quote(pathinfo($asset->getFilename(), PATHINFO_EXTENSION), '/') . '$/i', '', $asset->getFilename());

        // add custom suffix if available
        if ($config->getFilenameSuffix()) {
            $filename .= '~-~' . $config->getFilenameSuffix();
        }
        // add high-resolution modifier suffix to the filename
        if ($config->getHighResolution() > 1) {
            $filename .= '@' . $config->getHighResolution() . 'x';
        }

        $fileExtension = $format;
        if ($format == 'original') {
            $fileExtension = $fileExt;
        } elseif ($format === 'pjpeg' || $format === 'jpeg') {
            $fileExtension = 'jpg';
        }

        $filename .= '.' . $config->getHash([$asset->getChecksum()]) . '.'. $fileExtension;
        $storagePath = $thumbDir . '/' . $filename;
        $storage = Storage::get('thumbnail');

        if (!$storage->fileExists($storagePath)) {
            return null;
        }

        return urlencode_ignore_slash($storage->publicUrl($storagePath));
    }

    public static function getDocument(int $id, int $page = 1)
    {
        $document = Asset\Document::getById($id);

        if (!$document) {
            throw new NotFoundHttpException('could not load document asset');
        }

        if (!$document->isAllowed('view')) {
            return null;
        }

        $thumbnail = Asset\Image\Thumbnail\Config::getPreviewConfig();
        $thumb = $document->getImageThumbnail($thumbnail, $page);

        $config = $thumb->getConfig();
        $config->setFilenameSuffix('page-' . $page);
        $format = strtolower($config->getFormat());
        $fileExt = pathinfo($document->getFilename(), PATHINFO_EXTENSION);

        // simple detection for source type if SOURCE is selected
        if ($format == 'source' || empty($format)) {
            $thumbnail->setFormat('jpeg'); // default format for documents is JPEG not PNG (=too big)
            $optimizedFormat = true;
            $format = self::getAllowedFormat($fileExt, ['pjpeg', 'jpeg', 'gif', 'png'], 'png');
            if ($format === 'jpeg') {
                $format = 'pjpeg';
            }
        }

        $thumbDir = rtrim($document->getRealPath(), '/').'/'.$document->getId().'/image-thumb__'.$document->getId().'__'. $config->getName();
        $filename = preg_replace("/\." . preg_quote(pathinfo($document->getFilename(), PATHINFO_EXTENSION), '/') . '$/i', '', $document->getFilename());

        // add custom suffix if available
        if ($config->getFilenameSuffix()) {
            $filename .= '~-~' . $config->getFilenameSuffix();
        }
        // add high-resolution modifier suffix to the filename
        if ($config->getHighResolution() > 1) {
            $filename .= '@' . $config->getHighResolution() . 'x';
        }

        $fileExtension = $format;
        if ($format == 'original') {
            $fileExtension = $fileExt;
        } elseif ($format === 'pjpeg' || $format === 'jpeg') {
            $fileExtension = 'jpg';
        }

        $filename .= '.' . $config->getHash([$document->getChecksum()]) . '.'. $fileExtension;
        $storagePath = $thumbDir . '/' . $filename;
        $storage = Storage::get('thumbnail');

        if (!$storage->fileExists($storagePath)) {
            return null;
        }

        return urlencode_ignore_slash($storage->publicUrl($storagePath));
    }

    private static function getAllowedFormat(string $format, array $allowed = [], string $fallback = 'png'): string
    {
        $typeMappings = [
            'jpg' => 'jpeg',
            'tif' => 'tiff',
        ];

        if (isset($typeMappings[$format])) {
            $format = $typeMappings[$format];
        }

        if (in_array($format, $allowed)) {
            $target = $format;
        } else {
            $target = $fallback;
        }

        return $target;
    }
}
