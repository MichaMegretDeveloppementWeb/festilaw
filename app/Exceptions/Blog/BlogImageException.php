<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Image du blog refusee, impossible a traiter ou a stocker. Les messages utilisateur sont en
 * francais : ils s'affichent au back-office.
 */
final class BlogImageException extends BaseAppException
{
    public static function unreadable(?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: 'Blog image file is missing, unreadable or not an image.',
            userMessage: 'Ce fichier n\'est pas une image lisible. Envoyez un fichier JPEG, PNG ou WebP.',
            previous: $previous,
        );
    }

    public static function unsupportedType(string $mime): self
    {
        return new self(
            technicalMessage: "Blog image type [{$mime}] is not supported.",
            userMessage: 'Ce format d\'image n\'est pas pris en charge. Envoyez un fichier JPEG, PNG ou WebP.',
        );
    }

    public static function tooHeavy(int $kilobytes, int $maxKilobytes): self
    {
        $megabytes = (int) floor($maxKilobytes / 1024);

        return new self(
            technicalMessage: "Blog image is too heavy: {$kilobytes} KB (maximum {$maxKilobytes} KB).",
            userMessage: "L'image dépasse {$megabytes} Mo. Réduisez son poids avant de l'envoyer.",
        );
    }

    public static function tooManyPixels(int $width, int $height, int $maxPixels): self
    {
        return new self(
            technicalMessage: "Blog image has too many pixels: {$width}x{$height} (maximum {$maxPixels} pixels).",
            userMessage: "L'image est trop grande ({$width} × {$height} pixels). Réduisez ses dimensions avant de l'envoyer.",
        );
    }

    public static function tooSmall(int $width, int $height, int $minWidth, int $minHeight): self
    {
        return new self(
            technicalMessage: "Blog featured or share image is too small: {$width}x{$height} (minimum {$minWidth}x{$minHeight}).",
            userMessage: "Cette image est trop petite ({$width} × {$height} pixels). L'image principale et l'image de partage doivent mesurer au moins {$minWidth} × {$minHeight} pixels.",
        );
    }

    public static function cmyk(): self
    {
        return new self(
            technicalMessage: 'Blog image uses the CMYK colour space.',
            userMessage: 'Cette image est en couleurs d\'impression (CMJN). Enregistrez-la en RVB avant de l\'envoyer.',
        );
    }

    public static function animated(): self
    {
        return new self(
            technicalMessage: 'Blog image is animated.',
            userMessage: 'Les images animées ne sont pas prises en charge. Envoyez une image fixe.',
        );
    }

    public static function cropTooSmall(int $minWidth, int $minHeight): self
    {
        return new self(
            technicalMessage: "Blog image refused: the kept area is smaller than {$minWidth}x{$minHeight} px or lies outside the image.",
            userMessage: "La partie gardée est trop petite. Gardez au moins {$minWidth} × {$minHeight} pixels de l'image.",
        );
    }

    public static function notRecroppable(int $imageId): self
    {
        return new self(
            technicalMessage: "Blog image [{$imageId}] has no kept source: it cannot be cropped again.",
            userMessage: 'Cette image ne peut pas être recadrée. Retirez-la, puis ajoutez-la de nouveau.',
        );
    }

    public static function processingFailed(Throwable $previous): self
    {
        return new self(
            technicalMessage: 'Blog image processing failed: '.$previous->getMessage(),
            userMessage: 'L\'image n\'a pas pu être traitée. Réessayez avec un autre fichier.',
            previous: $previous,
        );
    }

    public static function storageFailed(string $uuid, Throwable $previous): self
    {
        return new self(
            technicalMessage: "Failed to write blog image files [{$uuid}].",
            userMessage: 'L\'image n\'a pas pu être enregistrée. Réessayez dans un instant.',
            previous: $previous,
        );
    }

    public static function notFound(int $imageId): self
    {
        return new self(
            technicalMessage: "Blog image [{$imageId}] not found.",
            userMessage: 'Cette image est introuvable. Rechargez la page.',
        );
    }

    public static function stillAttached(int $imageId): self
    {
        return new self(
            technicalMessage: "Blog image [{$imageId}] is attached to a post and cannot be deleted.",
            userMessage: 'Cette image est encore utilisée par un article.',
        );
    }

    /** Une image citee par un article n'existe plus, ou appartient deja a un autre article. */
    public static function notUsableByPost(int $imageId): self
    {
        return new self(
            technicalMessage: "Blog image [{$imageId}] is missing or belongs to another post.",
            userMessage: 'Une image de cet article est introuvable ou appartient à un autre article. Retirez-la puis envoyez-la de nouveau.',
        );
    }
}
