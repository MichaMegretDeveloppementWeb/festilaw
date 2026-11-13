<?php

declare(strict_types=1);

namespace Database\Seeders\BlogDemo;

use RuntimeException;

/**
 * Images des articles de demonstration. Une photo deposee dans le dossier local (hors depot) sous le
 * nom de l'article est utilisee telle quelle, avec son credit lu dans credits.json ; sinon un visuel
 * neutre aux couleurs du site est fabrique : le seeder fonctionne dans tous les cas, sans aucun
 * fichier binaire dans le depot.
 *
 *   demo-images/gpsr-in-plain-language.jpg
 *   demo-images/credits.json   {"gpsr-in-plain-language": {"author": "...", "url": "...", "license": "..."}}
 */
final class BlogDemoImages
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Taille minimale d'une image principale : au-dela, le traitement serait plus long sans rien montrer de plus. */
    private const WIDTH = 1200;

    private const HEIGHT = 675;

    /** Couleurs du site (resources/css/web/base/tokens.css) : bleu, corail, saumon, carte, encre. */
    private const PALETTE = [[105, 107, 242], [254, 119, 106], [240, 142, 128], [244, 236, 219], [14, 19, 38]];

    /** @var list<string> */
    private array $generated = [];

    /** @var array<string, array{author?: string, url?: string, license?: string}>|null */
    private ?array $credits = null;

    public function __construct(private readonly string $photoDirectory) {}

    /**
     * @return array{path: string, credit: array{author: ?string, url: ?string, license: ?string}|null}
     *                                                                                                  credit null = visuel fabrique
     */
    public function for(string $key, int $variation): array
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = $this->photoDirectory.DIRECTORY_SEPARATOR.$key.'.'.$extension;

            if (is_file($path)) {
                $credit = $this->credits()[$key] ?? [];

                return ['path' => $path, 'credit' => [
                    'author' => $credit['author'] ?? null,
                    'url' => $credit['url'] ?? null,
                    'license' => $credit['license'] ?? null,
                ]];
            }
        }

        return ['path' => $this->generate($variation), 'credit' => null];
    }

    /** Supprime les visuels fabriques (les photos du dossier local ne sont jamais touchees). */
    public function cleanup(): void
    {
        foreach ($this->generated as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->generated = [];
    }

    /**
     * @return array<string, array{author?: string, url?: string, license?: string}>
     */
    private function credits(): array
    {
        if ($this->credits !== null) {
            return $this->credits;
        }

        $file = $this->photoDirectory.DIRECTORY_SEPARATOR.'credits.json';
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return $this->credits = is_array($decoded) ? $decoded : [];
    }

    /** Aplats geometriques aux couleurs du site, differents d'une variation a l'autre. */
    private function generate(int $variation): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            throw new RuntimeException('Blog demo visual could not be created (GD).');
        }

        imageantialias($image, true);

        $colors = [];

        foreach (self::PALETTE as $offset => [$red, $green, $blue]) {
            $colors[$offset] = (int) imagecolorallocate($image, $red, $green, $blue);
        }

        $pick = static fn (int $rank): int => $colors[($variation + $rank) % count($colors)];
        $shift = ($variation % 5) * 105;

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $pick(0));
        imagefilledellipse($image, 285 + $shift, 315, 570, 570, $pick(1));
        imagefilledellipse($image, 885 - intdiv($shift, 2), 480, 390, 390, $pick(2));
        imagefilledpolygon($image, [
            self::WIDTH - 390 + intdiv($shift, 3), 0,
            self::WIDTH, 0,
            self::WIDTH, 270 + intdiv($shift, 2),
        ], $pick(3));
        imagefilledrectangle($image, 0, self::HEIGHT - 52, self::WIDTH, self::HEIGHT, $pick(4));

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'festilaw-blog-demo-'.bin2hex(random_bytes(6)).'.jpg';

        if (! imagejpeg($image, $path, 88)) {
            throw new RuntimeException('Blog demo visual could not be written: '.$path);
        }

        $this->generated[] = $path;

        return $path;
    }
}
