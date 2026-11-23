<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Blog;

use App\Exceptions\Blog\BlogImageException;
use App\Http\Controllers\Controller;
use App\Services\Admin\Blog\BlogImageSourceService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * La source d'une image du blog, pour l'outil de recadrage du back-office (connexion obligatoire). Elle est
 * rangee sur le disque prive : publique, elle montrait ce que le recadrage avait retire (audit du 23/11).
 */
final class BlogImageSourceController extends Controller
{
    public function __invoke(int $image, BlogImageSourceService $sources): Response
    {
        try {
            $contents = $sources->contents($image);
        } catch (BlogImageException) {
            abort(404);
        } catch (Throwable $e) {
            Log::error('Blog image source could not be read.', ['image_id' => $image, 'exception' => $e]);
            abort(500);
        }

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
