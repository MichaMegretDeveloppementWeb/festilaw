<?php

declare(strict_types=1);

namespace App\Data\Blog;

use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogImageUsage;

/**
 * Image a enregistrer. `path` est un fichier local (envoi du back-office ou fichier telecharge
 * depuis une banque d'images) : l'Action ne sait pas d'ou il vient.
 */
final readonly class StoreBlogImageData
{
    /**
     * @param  array<string, string>  $alt  description par langue
     * @param  string|null  $nameHint  texte dont tirer le nom du fichier (titre de l'article, description)
     * @param  BlogImageCrop|null  $crop  rectangle a garder, en pixels de l'image redressee (null : toute l'image)
     * @param  bool  $keepSource  garder la source non decoupee, pour pouvoir recadrer l'image plus tard
     */
    public function __construct(
        public string $path,
        public BlogImageUsage $usage,
        public array $alt = [],
        public ?string $nameHint = null,
        public BlogImageCreditSource $creditSource = BlogImageCreditSource::Upload,
        public ?string $creditAuthor = null,
        public ?string $creditUrl = null,
        public ?string $creditLicense = null,
        public ?BlogImageCrop $crop = null,
        public bool $keepSource = false,
    ) {}
}
