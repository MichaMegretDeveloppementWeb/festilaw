<?php

declare(strict_types=1);

namespace App\Services\Web\Localization;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * URLs of the public pages that have one address per language (routes/localized.php): the default
 * language is unprefixed, the others live under /fr and /es. A localized route carries the `locale`
 * route metadata; its name is the default-language name, prefixed by the locale for the other languages
 * (pricing, fr.pricing, es.pricing).
 *
 * Every link to one of these pages goes through route() (helper localized_route()) rather than Laravel's
 * route(), so that a visitor stays in their language. The locale defaults to the application locale,
 * which makes it correct inside Livewire updates and inside e-mails sent with a recipient locale.
 */
final readonly class LocalizedUrlGenerator
{
    /** Request attributes holding what the current page declared (see declareAlternates()). */
    private const ALTERNATES_ATTRIBUTE = 'localized.alternates';

    private const SWITCHER_ATTRIBUTE = 'localized.switcher';

    public function __construct(
        private Router $router,
        private UrlGenerator $url,
    ) {}

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values((array) config('festilaw.supported_locales'));
    }

    /**
     * The language whose public pages are unprefixed. Not config('app.locale'): Laravel overwrites that
     * key on every setLocale(), so it holds the current language, not the default one.
     */
    public function defaultLocale(): string
    {
        return (string) config('festilaw.default_locale');
    }

    /**
     * URL of a named route in the current language, or in the given one. A route that is not localized
     * (funnel, client space, back-office) is returned as Laravel's route() would.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = [], ?string $locale = null): string
    {
        if (! $this->isLocalized($name)) {
            return $this->url->route($name, $parameters);
        }

        return $this->url->route($this->localizedName($name, $this->resolveLocale($locale)), $parameters);
    }

    /**
     * The same page in every supported language.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, string> locale => URL
     */
    public function inAllLocales(string $name, array $parameters = []): array
    {
        $urls = [];

        foreach ($this->locales() as $locale) {
            $urls[$locale] = $this->route($name, $parameters, $locale);
        }

        return $urls;
    }

    /**
     * Whether the current page is one of the given localized pages, whatever its language. Replaces
     * request()->routeIs(), which would miss fr.pricing when asked for pricing.
     */
    public function isCurrent(string ...$names): bool
    {
        $baseName = $this->baseName($this->currentRoute());

        return $baseName !== null && in_array($baseName, $names, true);
    }

    /**
     * The current page in every language, for hreflang tags. Empty when the current page has no address
     * per language.
     *
     * @return array<string, string> locale => URL
     */
    public function alternates(): array
    {
        $declared = $this->declared(self::ALTERNATES_ATTRIBUTE);

        if ($declared !== null) {
            return $declared;
        }

        $route = $this->currentRoute();
        $baseName = $this->baseName($route);

        if ($route === null || $baseName === null) {
            return [];
        }

        return $this->inAllLocales($baseName, $this->uriParameters($route));
    }

    /**
     * Links of the language switcher: the same page in each language on a localized page, each language's
     * home page when no route matched (404), and the session switch everywhere else (funnel, client space).
     *
     * @return array<string, string> locale => URL
     */
    public function switcherLinks(): array
    {
        $declared = $this->declared(self::SWITCHER_ATTRIBUTE);

        if ($declared !== null) {
            return $declared;
        }

        $alternates = $this->alternates();

        if ($alternates !== []) {
            return $alternates;
        }

        if ($this->currentRoute() === null) {
            return $this->inAllLocales('home');
        }

        $links = [];

        foreach ($this->locales() as $locale) {
            $links[$locale] = $this->url->route('locale.switch', ['locale' => $locale]);
        }

        return $links;
    }

    /**
     * The address of a localized page in another language, query string kept. Null when the URL is not a
     * localized page of this site.
     */
    public function translate(string $url, string $locale): ?string
    {
        $request = Request::create($url);

        try {
            $route = $this->router->getRoutes()->match($request);
        } catch (HttpExceptionInterface) {
            return null;
        }

        $baseName = $this->baseName($route);

        if ($baseName === null) {
            return null;
        }

        $parameters = $this->uriParameters($route);
        $fallback = $route->getMetadata('locale_fallback');

        // A slug belongs to one language: the address cannot be rebuilt as is in another one. The route says
        // which parameterless page to land on instead (the blog hub for an article or a category).
        if ($parameters !== [] && is_string($fallback)) {
            return $this->route($fallback, [], $locale);
        }

        $target = $this->route($baseName, $parameters, $locale);
        $query = $request->getQueryString();

        return $query === null ? $target : $target.'?'.$query;
    }

    /**
     * Lets a page state its own equivalents in the other languages, when they cannot be derived from the
     * current address: a blog article or category has a different slug in each language. The declaration
     * lives on the current request (no shared state) and feeds both the hreflang tags and the language
     * switcher.
     *
     * @param  array<string, string>  $urlsByLocale  locale => URL
     */
    public function declareAlternates(array $urlsByLocale): void
    {
        $request = $this->router->getCurrentRequest();

        $request?->attributes->set(self::ALTERNATES_ATTRIBUTE, $urlsByLocale);
        $request?->attributes->remove(self::SWITCHER_ATTRIBUTE);
    }

    /**
     * For a page that has no equivalent in the other languages although it sits on a localized route (an
     * error page for an unknown or deleted article): no hreflang tag at all, and the language switcher
     * leads to the given pages instead (typically the blog hub of each language).
     *
     * @param  array<string, string>  $switcherLinks  locale => URL
     */
    public function declareNoAlternates(array $switcherLinks): void
    {
        $request = $this->router->getCurrentRequest();

        $request?->attributes->set(self::ALTERNATES_ATTRIBUTE, []);
        $request?->attributes->set(self::SWITCHER_ATTRIBUTE, $switcherLinks);
    }

    /**
     * @return array<string, string>|null null when the current page declared nothing
     */
    private function declared(string $attribute): ?array
    {
        $value = $this->router->getCurrentRequest()?->attributes->get($attribute);

        return is_array($value) ? $value : null;
    }

    private function isLocalized(string $name): bool
    {
        return $this->router->getRoutes()->getByName($name)?->getMetadata('locale') !== null;
    }

    private function localizedName(string $name, string $locale): string
    {
        return $locale === $this->defaultLocale() ? $name : $locale.'.'.$name;
    }

    private function resolveLocale(?string $locale): string
    {
        $locale ??= app()->getLocale();

        return in_array($locale, $this->locales(), true) ? $locale : $this->defaultLocale();
    }

    private function currentRoute(): ?Route
    {
        $route = $this->router->getCurrentRequest()?->route();

        return $route instanceof Route ? $route : null;
    }

    /**
     * The values of the placeholders of the route URI, as they appear in the address. A bound route also
     * carries its defaults as parameters (Route::view stores its view name there): passed on to the URL
     * generator, they would end up in the query string.
     *
     * @return array<string, mixed>
     */
    private function uriParameters(Route $route): array
    {
        return Arr::only($route->originalParameters(), $route->parameterNames());
    }

    /**
     * Name of a localized route without its locale prefix. Null for a route that is not localized.
     */
    private function baseName(?Route $route): ?string
    {
        $locale = $route?->getMetadata('locale');
        $name = $route?->getName();

        if (! is_string($locale) || $name === null) {
            return null;
        }

        return $locale === $this->defaultLocale() ? $name : Str::after($name, $locale.'.');
    }
}
