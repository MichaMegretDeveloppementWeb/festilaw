<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToWriteFile;

/**
 * Fichiers image fabriques a la volee pour les tests du blog (aucun fichier binaire dans le depot),
 * et disque public de test dont le stockage echoue comme un vrai disque.
 */
final class BlogImageFiles
{
    /** @var list<string> */
    private static array $created = [];

    public static function jpeg(int $width, int $height): string
    {
        return self::write('jpg', static fn (string $path) => imagejpeg(self::canvas($width, $height), $path, 90));
    }

    public static function png(int $width, int $height, bool $transparent = false): string
    {
        return self::write('png', static fn (string $path) => imagepng(self::canvas($width, $height, $transparent), $path));
    }

    public static function webp(int $width, int $height): string
    {
        return self::write('webp', static fn (string $path) => imagewebp(self::canvas($width, $height), $path, 90));
    }

    public static function gif(int $width, int $height): string
    {
        return self::write('gif', static fn (string $path) => imagegif(self::canvas($width, $height), $path));
    }

    /** Fichier texte renomme en image : le type se juge sur le contenu. */
    public static function fakeJpeg(): string
    {
        return self::write('jpg', static fn (string $path) => file_put_contents($path, '<?php echo "not an image";'));
    }

    /**
     * JPEG portant une orientation EXIF (6 = photo prise de cote, a tourner d'un quart de tour).
     * GD n'ecrit pas d'EXIF : le segment est insere a la main apres le marqueur de debut.
     */
    public static function jpegWithOrientation(int $width, int $height, int $orientation): string
    {
        $jpeg = (string) file_get_contents(self::jpeg($width, $height));

        $tiff = "II*\x00\x08\x00\x00\x00"                       // en-tete TIFF petit-boutiste, premier IFD a l'offset 8
            ."\x01\x00"                                         // une entree
            ."\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00" // balise Orientation, type SHORT
            ."\x00\x00\x00\x00";                                // pas d'IFD suivant
        $payload = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return self::write('jpg', static fn (string $path) => file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2)));
    }

    /** En-tete WebP etendu avec le drapeau d'animation leve (suffisant pour etre reconnu comme anime). */
    public static function animatedWebp(int $width = 400, int $height = 300): string
    {
        $chunk = 'VP8X'.pack('V', 10)."\x02\x00\x00\x00"
            .substr(pack('V', $width - 1), 0, 3)
            .substr(pack('V', $height - 1), 0, 3);
        $body = 'WEBP'.$chunk;

        return self::write('webp', static fn (string $path) => file_put_contents($path, 'RIFF'.pack('V', strlen($body)).$body));
    }

    /**
     * Remplace le disque public par un disque dont les ecritures ou les suppressions de dossier
     * echouent, comme un disque plein ou un dossier verrouille. Le reglage `throw` du vrai disque est
     * conserve : c'est lui qui decide si l'echec remonte.
     */
    public static function failingDisk(bool $failWrites = false, bool $failDirectoryDeletes = false): void
    {
        $healthy = Storage::fake('public');
        $config = $healthy->getConfig();

        $adapter = new class($healthy->path(''), $failWrites, $failDirectoryDeletes) extends LocalFilesystemAdapter
        {
            public function __construct(string $root, private bool $failWrites, private bool $failDirectoryDeletes)
            {
                parent::__construct($root);
            }

            public function write(string $path, string $contents, Config $config): void
            {
                // La deuxieme variante echoue : la premiere est deja ecrite, le nettoyage a de quoi faire.
                if ($this->failWrites && ! str_ends_with($path, '-480.webp')) {
                    throw UnableToWriteFile::atLocation($path, 'disk full (test)');
                }

                parent::write($path, $contents, $config);
            }

            public function deleteDirectory(string $path): void
            {
                if ($this->failDirectoryDeletes) {
                    throw UnableToDeleteDirectory::atLocation($path, 'directory locked (test)');
                }

                parent::deleteDirectory($path);
            }
        };

        Storage::set('public', new FilesystemAdapter(new Flysystem($adapter, $config), $adapter, $config));
    }

    public static function cleanup(): void
    {
        foreach (self::$created as $path) {
            @unlink($path);
        }

        self::$created = [];
    }

    private static function write(string $extension, callable $writer): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'festilaw-blog-'.bin2hex(random_bytes(6)).'.'.$extension;

        $writer($path);
        self::$created[] = $path;

        return $path;
    }

    private static function canvas(int $width, int $height, bool $transparent = false): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 160));
        }

        // Un motif, pour que l'image ne soit pas un aplat.
        imagefilledrectangle($image, (int) ($width * 0.1), (int) ($height * 0.1), (int) ($width * 0.6), (int) ($height * 0.6), imagecolorallocate($image, 220, 60, 40));
        imagefilledellipse($image, (int) ($width * 0.7), (int) ($height * 0.7), (int) ($width * 0.3), (int) ($height * 0.3), imagecolorallocate($image, 240, 220, 80));

        return $image;
    }
}
