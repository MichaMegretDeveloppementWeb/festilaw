<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Blog\Content\BlogPostHtmlRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Regenere le HTML enregistre de tous les articles a partir de leur document. A lancer a la main
 * apres une evolution du moteur de rendu (non planifiee). Rejouable : un HTML deja a jour n'est pas
 * reecrit.
 *  --dry  compte ce qui changerait, sans rien ecrire
 */
final class RerenderBlogContent extends Command
{
    protected $signature = 'festilaw:blog-rerender {--dry : Simulation, sans ecriture}';

    protected $description = 'Regenere le HTML de tous les articles du blog a partir de leur contenu.';

    public function handle(BlogPostHtmlRefresher $refresher): int
    {
        $dry = (bool) $this->option('dry');

        try {
            $result = $refresher->refreshAll(persist: ! $dry);
        } catch (Throwable $e) {
            Log::error('Blog content re-render failed.', ['exception' => $e]);
            $this->error('Blog: re-render failed (see logs).');

            return self::FAILURE;
        }

        $prefix = $dry ? 'Blog (dry run)' : 'Blog';
        $this->info("{$prefix}: {$result['posts']} post(s) read, {$result['changed']} language version(s) re-rendered.");

        return self::SUCCESS;
    }
}
