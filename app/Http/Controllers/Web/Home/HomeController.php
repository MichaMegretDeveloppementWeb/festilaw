<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Home;

use App\Http\Controllers\Controller;
use App\Services\Web\Blog\BlogHomeBlockService;
use Illuminate\View\View;

final class HomeController extends Controller
{
    /**
     * L'accueil recoit les derniers articles du blog (liste vide tant qu'aucun n'est publie, ou si leur
     * lecture echoue : le bloc est alors simplement masque).
     */
    public function __invoke(BlogHomeBlockService $blog): View
    {
        return view('web.home.index', [
            'latestPosts' => $blog->latest(app()->getLocale()),
        ]);
    }
}
