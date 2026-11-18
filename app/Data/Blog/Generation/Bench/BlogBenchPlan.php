<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation\Bench;

use App\Enums\Blog\Generation\BlogTopicKind;

/**
 * Ce que le banc d'essai joue (config/blog.php, section ai.bench) : les sujets, les configurations de modeles,
 * les modeles partages du sujet et de la recherche, le modele d'image et la longueur des articles.
 */
final readonly class BlogBenchPlan
{
    /**
     * @param  list<BlogBenchTopic>  $topics
     * @param  list<BlogBenchConfiguration>  $configurations
     * @param  array<string, string>  $sharedModels  modele du sujet et de la recherche
     */
    public function __construct(
        public array $topics,
        public array $configurations,
        public array $sharedModels,
        public string $imageModel,
        public int $words,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromConfig(array $config): self
    {
        $topics = [];
        foreach ((array) ($config['topics'] ?? []) as $key => $topic) {
            $topics[] = new BlogBenchTopic((string) $key, BlogTopicKind::from((string) $topic['kind']), (string) $topic['topic']);
        }

        $configurations = [];
        foreach ((array) ($config['configurations'] ?? []) as $key => $configuration) {
            $configurations[] = new BlogBenchConfiguration(
                (string) $key,
                (string) $configuration['name'],
                array_map(strval(...), (array) $configuration['models']),
            );
        }

        return new self(
            $topics,
            $configurations,
            array_map(strval(...), (array) ($config['shared_models'] ?? [])),
            (string) ($config['image_model'] ?? ''),
            (int) ($config['article_words'] ?? 1_000),
        );
    }

    /**
     * Le plan reduit aux sujets et aux configurations demandes (tous quand une liste est vide), dans l'ordre du
     * plan. Une cle inconnue est rendue a l'appelant, qui refuse de jouer.
     *
     * @param  list<string>  $topicKeys
     * @param  list<string>  $configurationKeys
     * @return array{0: self, 1: list<string>}
     */
    public function only(array $topicKeys, array $configurationKeys): array
    {
        $knownTopics = array_map(static fn (BlogBenchTopic $topic): string => $topic->key, $this->topics);
        $knownConfigurations = array_map(static fn (BlogBenchConfiguration $configuration): string => $configuration->key, $this->configurations);
        $unknown = [...array_diff($topicKeys, $knownTopics), ...array_diff($configurationKeys, $knownConfigurations)];

        return [
            new self(
                array_values(array_filter($this->topics, static fn (BlogBenchTopic $topic): bool => $topicKeys === [] || in_array($topic->key, $topicKeys, true))),
                array_values(array_filter($this->configurations, static fn (BlogBenchConfiguration $configuration): bool => $configurationKeys === [] || in_array($configuration->key, $configurationKeys, true))),
                $this->sharedModels,
                $this->imageModel,
                $this->words,
            ),
            array_values($unknown),
        ];
    }
}
