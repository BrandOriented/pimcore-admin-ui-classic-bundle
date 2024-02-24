<?php

namespace Pimcore\Bundle\AdminBundle\Service;

class ThumbnailService
{
    public static function getAllowedFormat(string $format, array $allowed = [], string $fallback = 'png'): string
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
