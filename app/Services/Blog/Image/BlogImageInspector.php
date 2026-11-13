<?php

declare(strict_types=1);

namespace App\Services\Blog\Image;

use App\Data\Blog\BlogImageMetadata;
use App\Enums\Blog\BlogImageUsage;
use App\Exceptions\Blog\BlogImageException;

/**
 * Controle un fichier image AVANT de le decoder : seules ses metadonnees sont lues. Un fichier de
 * quelques kilo-octets peut se decompresser en plusieurs giga-octets ; c'est ici qu'on l'arrete. Le
 * type est juge sur le contenu du fichier, jamais sur son nom ni sur ce qu'annonce le navigateur.
 */
final readonly class BlogImageInspector
{
    private const ALLOWED_TYPES = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    public function __construct(
        private int $maxKilobytes,
        private int $maxPixels,
        private int $minFeaturedWidth,
        private int $minFeaturedHeight,
    ) {}

    /**
     * @throws BlogImageException
     */
    public function inspect(string $path, BlogImageUsage $usage): BlogImageMetadata
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw BlogImageException::unreadable();
        }

        $kilobytes = (int) ceil(((int) filesize($path)) / 1024);

        if ($kilobytes > $this->maxKilobytes) {
            throw BlogImageException::tooHeavy($kilobytes, $this->maxKilobytes);
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw BlogImageException::unreadable();
        }

        [$width, $height, $type] = $info;

        if (! isset(self::ALLOWED_TYPES[$type])) {
            throw BlogImageException::unsupportedType((string) ($info['mime'] ?? 'unknown'));
        }

        if ($width < 1 || $height < 1) {
            throw BlogImageException::unreadable();
        }

        if ($width * $height > $this->maxPixels) {
            throw BlogImageException::tooManyPixels($width, $height, $this->maxPixels);
        }

        if ($type === IMAGETYPE_JPEG && ($info['channels'] ?? 3) === 4) {
            throw BlogImageException::cmyk();
        }

        if ($type === IMAGETYPE_WEBP && $this->isAnimatedWebp($path)) {
            throw BlogImageException::animated();
        }

        if ($type === IMAGETYPE_JPEG && $this->isRotatedQuarterTurn($path)) {
            [$width, $height] = [$height, $width];
        }

        if ($usage->needsShareVariant() && ($width < $this->minFeaturedWidth || $height < $this->minFeaturedHeight)) {
            throw BlogImageException::tooSmall($width, $height, $this->minFeaturedWidth, $this->minFeaturedHeight);
        }

        return new BlogImageMetadata($width, $height, self::ALLOWED_TYPES[$type]);
    }

    /** En-tete WebP etendu (VP8X) dont le drapeau d'animation est leve. */
    private function isAnimatedWebp(string $path): bool
    {
        $header = (string) @file_get_contents($path, false, null, 0, 21);

        return strlen($header) === 21
            && substr($header, 12, 4) === 'VP8X'
            && (ord($header[20]) & 0x02) === 0x02;
    }

    /** Photo prise de cote : l'orientation EXIF (5 a 8) echange largeur et hauteur a l'affichage. */
    private function isRotatedQuarterTurn(string $path): bool
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        $exif = @exif_read_data($path);

        return is_array($exif) && in_array((int) ($exif['Orientation'] ?? 1), [5, 6, 7, 8], true);
    }
}
