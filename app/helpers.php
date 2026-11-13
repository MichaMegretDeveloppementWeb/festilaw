<?php

declare(strict_types=1);

use App\Services\Web\Localization\LocalizedUrlGenerator;

if (! function_exists('localized_route')) {
    /**
     * URL of a public page in the current display language, or in the given one. To be used instead of
     * route() for every page declared in routes/localized.php, so that a visitor stays in their language.
     *
     * @param  array<string, mixed>  $parameters
     */
    function localized_route(string $name, array $parameters = [], ?string $locale = null): string
    {
        return app(LocalizedUrlGenerator::class)->route($name, $parameters, $locale);
    }
}
