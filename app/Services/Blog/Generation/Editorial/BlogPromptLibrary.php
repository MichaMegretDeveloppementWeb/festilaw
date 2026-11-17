<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Exceptions\Blog\BlogGenerationException;

/**
 * Les consignes des agents du blog, ecrites en fichiers versionnes (`resources/prompts/blog/*.md`) pour
 * qu'on les ajuste sans toucher au code. Un fichier se demande par son nom, sans extension ni dossier ; un
 * fichier absent ou vide arrete l'etape avec une erreur claire. Les valeurs d'une consigne s'ecrivent
 * `{{nom}}` et se remplacent a l'appel. Les listes (une entree par ligne) servent au controle des signes.
 */
final class BlogPromptLibrary
{
    /** @var array<string, string> */
    private array $loaded = [];

    public function __construct(private readonly string $directory) {}

    /** @throws BlogGenerationException */
    public function get(string $name): string
    {
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }

        $path = rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.$name.'.md';
        $text = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) === 1 && is_file($path) ? file_get_contents($path) : false;

        // Les commentaires du fichier (sources, notes pour qui le maintient) ne partent pas vers le modele.
        $text = is_string($text) ? trim((string) preg_replace('/<!--.*?-->\s*/s', '', $text)) : '';

        if ($text === '') {
            throw BlogGenerationException::missingPrompt($name);
        }

        return $this->loaded[$name] = $text;
    }

    /**
     * Une consigne avec ses valeurs. Une valeur non fournie reste visible, ce qui se voit a la relecture.
     *
     * @param  array<string, string|int>  $values
     *
     * @throws BlogGenerationException
     */
    public function render(string $name, array $values = []): string
    {
        $replacements = [];

        foreach ($values as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return strtr($this->get($name), $replacements);
    }

    /**
     * Plusieurs consignes a la suite, separees d'une ligne vide.
     *
     * @throws BlogGenerationException
     */
    public function compose(string ...$names): string
    {
        return implode("\n\n", array_map($this->get(...), $names));
    }

    /**
     * Les entrees d'une liste : une par ligne, sans les lignes vides ni les commentaires (`#`).
     *
     * @return list<string>
     *
     * @throws BlogGenerationException
     */
    public function lines(string $name): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode("\n", $this->get($name))),
            static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }
}
