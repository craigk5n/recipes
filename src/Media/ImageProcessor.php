<?php

declare(strict_types=1);

namespace Recipes\Media;

use finfo;

/**
 * Validates and re-encodes uploaded or downloaded recipe photos.
 *
 * Re-encoding through GD strips anything that is not pixel data, and the
 * dimension check runs before decoding so a small file that claims a huge
 * canvas (a decompression bomb) cannot exhaust memory.
 */
class ImageProcessor
{
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const MAX_DIMENSION = 1600;
    // ~4 bytes per pixel once decoded: 16 MP stays well under a 128M memory_limit.
    private const MAX_SOURCE_PIXELS = 16_000_000;

    /**
     * @return array{data: string, mime: string}|null Null if not an allowed, decodable image.
     */
    public static function normalize(string $data): ?array
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return null;
        }

        $size = @getimagesizefromstring($data);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_SOURCE_PIXELS) {
            return null;
        }

        $image = @imagecreatefromstring($data);
        if ($image === false) {
            return null;
        }

        $image = self::shrink($image, $mime);
        $encoded = self::encode($image, $mime);
        imagedestroy($image);

        return $encoded === '' ? null : ['data' => $encoded, 'mime' => $mime];
    }

    private static function shrink(\GdImage $image, string $mime): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w <= self::MAX_DIMENSION && $h <= self::MAX_DIMENSION) {
            return $image;
        }

        $scale = self::MAX_DIMENSION / max($w, $h);
        $newW = max(1, (int)round($w * $scale));
        $newH = max(1, (int)round($h * $scale));
        $resized = imagecreatetruecolor($newW, $newH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($image);
        return $resized;
    }

    private static function encode(\GdImage $image, string $mime): string
    {
        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 85),
            'image/png'  => imagepng($image, null, 6),
            'image/gif'  => imagegif($image),
            'image/webp' => imagewebp($image, null, 85),
            default      => false,
        };
        return (string)ob_get_clean();
    }
}
