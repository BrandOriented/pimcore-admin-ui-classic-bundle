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

use League\Flysystem\FilesystemException;
use Pimcore\Loader\ImplementationLoader\Exception\UnsupportedException;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\MetaData\ClassDefinition\Data\Data;
use Pimcore\Model\Asset\Service;
use Pimcore\Model\Element;

class AssetGridService
{
    /**
     * @throws FilesystemException
     */
    public static function gridAssetData(Asset $asset, array $fields = null, string $requestedLanguage = null, array $params = []): array
    {
        $data = Element\Service::gridElementData($asset);
        $loader = null;

        if ($asset instanceof Asset && !empty($fields)) {
            $data = [
                'id' => $asset->getId(),
                'id~system' => $asset->getId(),
                'type~system' => $asset->getType(),
                'fullpath~system' => $asset->getRealFullPath(),
                'filename~system' => $asset->getKey(),
                'creationDate~system' => $asset->getCreationDate(),
                'modificationDate~system' => $asset->getModificationDate(),
                'idPath~system' => Element\Service::getIdPath($asset),
            ];

            $requestedLanguage = str_replace('default', '', $requestedLanguage);

            foreach ($fields as $field) {
                $fieldDef = explode('~', $field);
                if (isset($fieldDef[1]) && $fieldDef[1] === 'system') {
                    if ($fieldDef[0] === 'preview') {
                        switch ($asset) {
                            case $asset instanceof Asset\Image:
                                $thumbnailUrl = ThumbnailLinkService::getImage($asset->getId());
                                if($thumbnailUrl === null) {
                                    $thumbnailUrl = Service::getPreviewThumbnail($asset, ['treepreview' => true, 'width' => 108, 'height' => 70, 'frame' => true]);
                                }

                                break;
                            case $asset instanceof Asset\Folder:
                                $thumbnailUrl = ThumbnailLinkService::getFolder($asset->getId());
                                if($thumbnailUrl === null) {
                                    $thumbnailUrl = Service::getPreviewThumbnail($asset, ['treepreview' => true, 'width' => 108, 'height' => 70, 'frame' => true]);
                                }

                                break;
                            case $asset instanceof Asset\Video && \Pimcore\Video::isAvailable():
                                $thumbnailUrl = ThumbnailLinkService::getVideo($asset->getId());
                                if($thumbnailUrl === null) {
                                    $thumbnailUrl = Service::getPreviewThumbnail($asset, ['treepreview' => true, 'width' => 108, 'height' => 70, 'frame' => true]);
                                }

                                break;
                            case $asset instanceof Asset\Document && \Pimcore\Document::isAvailable() && $asset->getPageCount():
                                $thumbnailUrl = ThumbnailLinkService::getDocument($asset->getId());
                                if($thumbnailUrl === null) {
                                    $thumbnailUrl = Service::getPreviewThumbnail($asset, ['treepreview' => true, 'width' => 108, 'height' => 70, 'frame' => true]);
                                }

                                break;
                            case $asset instanceof Asset\Audio:
                                $thumbnailUrl = '/bundles/pimcoreadmin/img/flat-color-icons/speaker.svg';

                                break;
                            default:
                                $thumbnailUrl = '/bundles/pimcoreadmin/img/filetype-not-supported.svg';
                        }
                        $data[$field] = $thumbnailUrl;
                    } elseif ($fieldDef[0] === 'size') {
                        $size = $asset->getFileSize();
                        $data[$field] = formatBytes($size);
                    }
                } else {
                    if (isset($fieldDef[1])) {
                        $language = ($fieldDef[1] === 'none' ? '' : $fieldDef[1]);
                        $rawMetaData = $asset->getMetadata($fieldDef[0], $language, true, true);
                    } else {
                        $rawMetaData = $asset->getMetadata($field, $requestedLanguage, true, true);
                    }

                    $metaData = $rawMetaData['data'] ?? null;

                    if ($rawMetaData) {
                        $type = $rawMetaData['type'];
                        if (!$loader) {
                            $loader = \Pimcore::getContainer()->get('pimcore.implementation_loader.asset.metadata.data');
                        }

                        $metaData = $rawMetaData['data'] ?? null;

                        try {
                            /** @var Data $instance */
                            $instance = $loader->build($type);
                            $metaData = $instance->getDataForListfolderGrid($rawMetaData['data'] ?? null, $rawMetaData);
                        } catch (UnsupportedException $e) {
                        }
                    }

                    $data[$field] = $metaData;
                }
            }
        }

        return $data;
    }
}
