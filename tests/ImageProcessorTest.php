<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Recipes\Media\ImageProcessor;

class ImageProcessorTest extends TestCase
{
    private static function png(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        imagedestroy($img);
        return (string)ob_get_clean();
    }

    public function testNormalizeKeepsSmallImage(): void
    {
        $result = ImageProcessor::normalize(self::png(40, 30));
        $this->assertNotNull($result);
        $this->assertSame('image/png', $result['mime']);
        $size = getimagesizefromstring($result['data']);
        $this->assertSame([40, 30], [$size[0], $size[1]]);
    }

    public function testNormalizeShrinksLargeImage(): void
    {
        $result = ImageProcessor::normalize(self::png(3200, 800));
        $this->assertNotNull($result);
        $size = getimagesizefromstring($result['data']);
        $this->assertSame([1600, 400], [$size[0], $size[1]]);
    }

    public function testNormalizeRejectsNonImage(): void
    {
        $this->assertNull(ImageProcessor::normalize('<svg onload="alert(1)"></svg>'));
        $this->assertNull(ImageProcessor::normalize('GIF89a<script>alert(1)</script>'));
    }

    public function testNormalizeRejectsDecompressionBomb(): void
    {
        // A tiny PNG header that claims 20000x20000 pixels (400 MP) would need
        // ~1.6 GB of memory to decode; it must be refused before decoding.
        $ihdr = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
        $this->assertNull(ImageProcessor::normalize($png));
    }
}
