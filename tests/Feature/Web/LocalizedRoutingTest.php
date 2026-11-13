<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use function Pest\Laravel\from;
use function Pest\Laravel\get;

/*
 | Pages publiques : UNE ADRESSE PAR LANGUE. L'anglais garde ses adresses sans prefixe, le francais et
 | l'espagnol sont servis sous /fr et /es. La langue de la page vient de son adresse et elle est recopiee en
 | session, pour que le tunnel et l'espace client (une seule adresse, langue de session) suivent.
 */

// Les pages publiques lisent la base : lien "Blog" de l'en-tete et du pied de page, bloc de l'accueil.
uses(RefreshDatabase::class);

dataset('localized_pages', [
    'home', 'about', 'understand-gpsr', 'services', 'pricing', 'excluded-products', 'contact',
    'legal-notice', 'privacy-policy', 'terms', 'blog.index',
]);

dataset('site_locales', ['en', 'fr', 'es']);

it('serves every public page in each language at its own address', function (string $routeName, string $locale) {
    $url = localized_route($routeName, [], $locale);

    $response = get($url)
        ->assertOk()
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false);

    // Chaque version declare ses equivalents, et l'anglais comme version par defaut.
    foreach (['en', 'fr', 'es'] as $alternate) {
        $response->assertSee('<link rel="alternate" hreflang="'.$alternate.'" href="'.localized_route($routeName, [], $alternate).'">', false);
    }

    $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.localized_route($routeName, [], 'en').'">', false);
})->with('localized_pages')->with('site_locales');

it('keeps the English addresses unprefixed and prefixes the other languages', function () {
    expect(route('pricing'))->toBe(url('/pricing'))
        ->and(route('home'))->toBe(url('/'))
        ->and(route('fr.pricing'))->toBe(url('/fr/pricing'))
        ->and(route('es.home'))->toBe(url('/es'));
});

it('keeps the navigation of a page in its own language', function (string $locale, string $prefix) {
    get($prefix.'/pricing')
        ->assertOk()
        // En-tete et pied de page pointent vers les pages de la meme langue...
        ->assertSee('href="'.url($prefix.'/about').'"', false)
        ->assertSee('href="'.url($prefix.'/contact').'"', false)
        ->assertSee('href="'.url($prefix.'/legal-notice').'"', false)
        // ... jamais vers la version anglaise d'une AUTRE page.
        ->assertDontSee('href="'.url('/about').'"', false)
        // Le tunnel n'a qu'une adresse (langue de session).
        ->assertSee('href="'.route('get-started.index').'"', false);
})->with([
    'fr' => ['fr', '/fr'],
    'es' => ['es', '/es'],
]);

it('marks the current page as active in the header in every language', function (string $path) {
    get($path)
        ->assertOk()
        ->assertSee('href="'.url($path).'" class="site-header__link is-active"', false);
})->with(['/pricing', '/fr/pricing', '/es/pricing']);

it('formats og:locale and lists the other languages as alternates', function () {
    get('/fr/about')
        ->assertOk()
        ->assertSee('<meta property="og:locale" content="fr_FR">', false)
        ->assertSee('<meta property="og:locale:alternate" content="en_US">', false)
        ->assertSee('<meta property="og:locale:alternate" content="es_ES">', false)
        ->assertDontSee('<meta property="og:locale:alternate" content="fr_FR">', false);
});

it('copies the language of a public page to the session so the funnel follows', function () {
    get('/fr/pricing')->assertOk();

    expect(session('locale'))->toBe('fr');

    get(route('get-started.index'))
        ->assertOk()
        ->assertSee('<html lang="fr">', false)
        // Depuis le tunnel, les liens vers le site public restent en francais.
        ->assertSee('href="'.url('/fr/pricing').'"', false);

    // Une adresse anglaise fait foi a son tour.
    get('/pricing')->assertOk();

    expect(session('locale'))->toBe('en');

    get(route('get-started.index'))->assertSee('<html lang="en">', false);
});

it('has no address for an unsupported language nor a prefix for the default one', function (string $path) {
    get($path)->assertNotFound();
})->with(['/de/pricing', '/en/pricing', '/en', '/fr/get-started']);

it('links the language switcher straight to the same public page in each language', function () {
    get('/fr/services')
        ->assertOk()
        ->assertSee('href="'.url('/services').'"', false)
        ->assertSee('href="'.url('/es/services').'"', false)
        ->assertDontSee(route('locale.switch', ['locale' => 'es']), false);
});

it('keeps the session switch on pages that have a single address', function () {
    get(route('get-started.index'))
        ->assertOk()
        ->assertSee(route('locale.switch', ['locale' => 'fr']), false)
        ->assertSee(route('locale.switch', ['locale' => 'es']), false)
        ->assertDontSee('hreflang', false);
});

it('sends a visitor who switches language from a public page to its equivalent', function () {
    from(url('/fr/pricing'))
        ->get(route('locale.switch', ['locale' => 'es']))
        ->assertRedirect(url('/es/pricing'));

    expect(session('locale'))->toBe('es');
});

it('sends a visitor who switches language without a previous page to the home page of that language', function () {
    get(route('locale.switch', ['locale' => 'fr']))->assertRedirect(url('/fr'));
});

it('never redirects a language switch to another host', function () {
    get(route('locale.switch', ['locale' => 'fr']), ['referer' => 'https://evil.example/pricing'])
        ->assertRedirect(url('/fr'));
});

it('forbids public views from linking to a localized page with route()', function () {
    // La liste est lue dans le routeur : une page ajoutee a routes/localized.php est couverte d'office.
    $localizedPages = collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn ($route): bool => $route->getMetadata('locale') === 'fr')
        ->keys()
        ->map(fn (string $name): string => preg_quote(Str::after($name, 'fr.'), '/'))
        ->implode('|');
    $offenders = [];

    expect(explode('|', $localizedPages))->toContain('pricing', 'terms', 'blog\.index', 'blog\.show');

    foreach (File::allFiles(resource_path('views')) as $file) {
        $path = str_replace('\\', '/', $file->getRelativePathname());

        // Le back-office (francais, une seule langue) pointe volontairement vers le site anglais.
        if ($path === 'layouts/admin.blade.php' || str_starts_with($path, 'livewire/admin/')) {
            continue;
        }

        if (preg_match('/(?<![A-Za-z_])route\(\s*[\'"]('.$localizedPages.')[\'"]/', $file->getContents()) === 1) {
            $offenders[] = $path;
        }
    }

    // Une page publique a une adresse par langue : le lien doit passer par localized_route().
    expect($offenders)->toBe([]);
});
