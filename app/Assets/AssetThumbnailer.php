<?php

namespace App\Assets;

use RuntimeException;

/**
 * Generates one fixed-box thumbnail per raster asset at upload time, via GD
 * (already present in the container — no new PHP extension or Composer
 * dependency). SVG is never rasterized: PelicanServerStatusNormalizer-style
 * "this format doesn't need what the others need" — a vector image is
 * already tiny and scales perfectly in a grid, so callers should check
 * Asset::hasThumbnail() and just use the original for SVG.
 */
class AssetThumbnailer
{
    /**
     * @return string the thumbnail's own bytes, ready to be written to
     *                 storage by the caller (this class never touches
     *                 Storage/disks directly — same reasoning as
     *                 ConnectorBackedProvider staying I/O-agnostic).
     */
    public function make(string $sourceBytes, string $mimeType, int $maxWidth, int $maxHeight): string
    {
        $image = $this->decode($sourceBytes, $mimeType);

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1.0);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency for PNG/WebP instead of flattening to a
        // black background — GD's canvas defaults to opaque black otherwise.
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
        imagefilledrectangle($thumbnail, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled(
            $thumbnail, $image,
            0, 0, 0, 0,
            $targetWidth, $targetHeight, $sourceWidth, $sourceHeight,
        );

        imagedestroy($image);

        $bytes = $this->encode($thumbnail, $mimeType);
        imagedestroy($thumbnail);

        return $bytes;
    }

    private function decode(string $bytes, string $mimeType): \GdImage
    {
        $image = match ($mimeType) {
            'image/png' => imagecreatefromstring($bytes),
            'image/jpeg' => imagecreatefromstring($bytes),
            'image/webp' => imagecreatefromstring($bytes),
            default => throw new RuntimeException("Cannot thumbnail unsupported MIME type [{$mimeType}]."),
        };

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('Could not decode image data — the file may be corrupt.');
        }

        return $image;
    }

    private function encode(\GdImage $image, string $mimeType): string
    {
        ob_start();

        match ($mimeType) {
            'image/png' => imagepng($image),
            'image/jpeg' => imagejpeg($image, quality: 85),
            'image/webp' => imagewebp($image, quality: 85),
        };

        return ob_get_clean();
    }
}
