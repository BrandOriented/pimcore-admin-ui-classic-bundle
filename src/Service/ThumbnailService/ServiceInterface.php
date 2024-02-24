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

use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Thumbnail\ThumbnailInterface;
use Symfony\Component\HttpFoundation\Request;

interface ServiceInterface
{
    public function async(int $id): void;

    public function getThumbnail(Request $request): array;

    public function getThumbnailConfig(Asset $asset, Request $request): ThumbnailInterface;
}
