<?php

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
