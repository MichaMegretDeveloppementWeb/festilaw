<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Admin\Blog\SaveAndPublishBlogPostAction;
use App\Actions\Admin\Blog\SaveAndScheduleBlogPostAction;
use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Actions\Blog\Post\UnpublishBlogPostAction;
use App\Data\Admin\Blog\BlogPostFormOpening;
use App\Data\Admin\Blog\BlogPostStatusChangeResult;
use App\Data\Blog\BlogPostTranslationData;
use App\Data\Blog\BlogSourceData;
use App\Data\Blog\SaveBlogPostData;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Models\BlogPost;
use App\Services\Admin\Blog\BlogPostFormService;
use App\Services\Admin\Blog\BlogSlugSuggestionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Throwable;

/**
 * Back-office : creation et modification d'un article du blog, dans les trois langues.
 *
 * Le composant est un bord : il garde l'etat des champs, valide leur forme, delegue aux Actions de SC2
 * et traduit leur issue. Le CONTENU des trois langues n'est pas dans son etat : il vit dans le
 * navigateur (resources/js/admin/blog-post-form.js) et arrive en argument au moment d'enregistrer, pour
 * que les allers-retours restent legers. Aucune requete ici.
 */
#[Layout('layouts.admin')]
class PostForm extends Component
{
    use HandlesAdminErrors;

    private const LOCALES = ['en' => 'anglais', 'fr' => 'français', 'es' => 'espagnol'];

    private const MAX_SOURCES = 30;

    #[Locked]
    public ?int $postId = null;

    /** @var array<string, array{title: string, slug: string, excerpt: string, metaTitle: string, metaDescription: string}> */
    public array $texts = [];

    /**
     * Par langue : l'adresse a ete saisie a la main (ou enregistree), elle n'est plus proposee depuis le titre.
     *
     * @var array<string, bool>
     */
    public array $slugEdited = [];

    /** '' (aucune) ou l'identifiant d'une categorie. */
    public string $categoryId = '';

    #[Locked]
    public ?int $featuredImageId = null;

    #[Locked]
    public ?int $shareImageId = null;

    /** @var list<int> */
    #[Locked]
    public array $relatedIds = [];

    /** @var list<array{label: string, url: string}> */
    public array $sources = [];

    /** Date de programmation, heure UTC, au format du champ (BlogPostFormService::DATETIME_FORMAT). */
    public string $scheduledFor = '';

    /** Charge a l'ouverture seulement : n'existe que pendant la premiere requete (jamais serialise). */
    private ?BlogPostFormOpening $opening = null;

    private bool $firstRender = false;

    public function mount(BlogPostFormService $form, int|string|null $post = null): void
    {
        $this->firstRender = true;

        foreach (array_keys(self::LOCALES) as $locale) {
            $this->texts[$locale] = ['title' => '', 'slug' => '', 'excerpt' => '', 'metaTitle' => '', 'metaDescription' => ''];
            $this->slugEdited[$locale] = false;
        }

        if ($post === null) {
            return;
        }

        $this->opening = $form->open((int) $post);

        abort_if($this->opening === null, 404);

        $state = $this->opening->state;

        $this->postId = $state->postId;
        $this->texts = $state->texts;
        $this->categoryId = (string) ($state->categoryId ?? '');
        $this->featuredImageId = $state->featuredImageId;
        $this->shareImageId = $state->shareImageId;
        $this->relatedIds = $state->relatedIds;
        $this->sources = $state->sources;
        $this->scheduledFor = $state->scheduledFor;

        // Une adresse deja enregistree ne change plus quand on retouche le titre.
        foreach (array_keys(self::LOCALES) as $locale) {
            $this->slugEdited[$locale] = $state->texts[$locale]['slug'] !== '';
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'texts.*.title' => ['nullable', 'string', 'max:255'],
            'texts.*.slug' => ['nullable', 'string', 'max:100'],
            'texts.*.excerpt' => ['nullable', 'string', 'max:500'],
            'texts.*.metaTitle' => ['nullable', 'string', 'max:255'],
            'texts.*.metaDescription' => ['nullable', 'string', 'max:255'],
            'categoryId' => ['nullable', 'integer'],
            'sources' => ['array', 'max:'.self::MAX_SOURCES],
            'sources.*.label' => ['required', 'string', 'max:200'],
            'sources.*.url' => ['required', 'string', 'max:2000', 'regex:#^https?://\S+$#i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $messages = [
            'categoryId.integer' => __('La catégorie choisie est invalide.'),
            'sources.max' => __('Un article ne peut pas avoir plus de :max sources.', ['max' => self::MAX_SOURCES]),
            'sources.*.label.required' => __('L\'intitulé de la source est obligatoire.'),
            'sources.*.label.max' => __('L\'intitulé de la source ne peut pas dépasser 200 caractères.'),
            'sources.*.url.required' => __('Le lien de la source est obligatoire.'),
            'sources.*.url.max' => __('Le lien de la source est trop long.'),
            'sources.*.url.regex' => __('Le lien de la source doit commencer par http:// ou https://.'),
            'scheduledFor.required' => __('Indiquez la date et l\'heure de publication.'),
            'scheduledFor.date_format' => __('La date de publication est invalide.'),
        ];

        foreach (self::LOCALES as $locale => $language) {
            $messages["texts.{$locale}.title.max"] = "Le titre en {$language} ne peut pas dépasser 255 caractères.";
            $messages["texts.{$locale}.slug.max"] = "L'adresse en {$language} ne peut pas dépasser 100 caractères.";
            $messages["texts.{$locale}.excerpt.max"] = "L'extrait en {$language} ne peut pas dépasser 500 caractères.";
            $messages["texts.{$locale}.metaTitle.max"] = "Le titre Google en {$language} ne peut pas dépasser 255 caractères.";
            $messages["texts.{$locale}.metaDescription.max"] = "La description Google en {$language} ne peut pas dépasser 255 caractères.";
        }

        return $messages;
    }

    /**
     * Enregistre l'article sans changer son statut.
     *
     * @param  array<string, mixed>  $contents  document de contenu par langue, tel que le navigateur le detient
     * @return int|null identifiant de l'article, ou null si rien n'a ete enregistre
     */
    public function save(array $contents, SaveBlogPostAction $savePost): ?int
    {
        $this->validate();

        $data = $this->data($contents);

        if ($data === null) {
            return null;
        }

        try {
            $post = $savePost->execute($data);
        } catch (BaseAppException $e) {
            $this->reportRefusal($e);

            return null;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin save blog post');

            return null;
        }

        $this->resetErrorBag('form');

        if ($this->postId === null) {
            session()->flash('toast-success', __('Article créé.'));
            $this->redirectRoute('admin.blog.posts.edit', ['post' => $post->id]);

            return $post->id;
        }

        $this->adoptSavedSlugs($post);
        $this->dispatch('blog-post-saved');
        $this->dispatch('ui-toast', title: __('Article enregistré.'), type: 'success');

        return $post->id;
    }

    /**
     * Enregistre puis publie. Un article pas encore pret reste enregistre en brouillon.
     *
     * @param  array<string, mixed>  $contents
     */
    public function publish(array $contents, SaveAndPublishBlogPostAction $saveAndPublish): ?int
    {
        $this->validate();

        $data = $this->data($contents);

        if ($data === null) {
            return null;
        }

        try {
            $result = $saveAndPublish->execute($data);
        } catch (BaseAppException $e) {
            $this->reportRefusal($e);

            return null;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin publish blog post');

            return null;
        }

        return $this->reopen($result, __('Article publié.'));
    }

    /**
     * Enregistre puis programme la publication a la date saisie (UTC).
     *
     * @param  array<string, mixed>  $contents
     */
    public function schedule(array $contents, SaveAndScheduleBlogPostAction $saveAndSchedule): ?int
    {
        $this->validate([
            ...$this->rules(),
            'scheduledFor' => ['required', 'date_format:'.BlogPostFormService::DATETIME_FORMAT],
        ]);

        $data = $this->data($contents);

        if ($data === null) {
            return null;
        }

        $at = CarbonImmutable::createFromFormat(BlogPostFormService::DATETIME_FORMAT, $this->scheduledFor, 'UTC');

        try {
            $result = $saveAndSchedule->execute($data, $at);
        } catch (BaseAppException $e) {
            $this->reportRefusal($e);

            return null;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin schedule blog post');

            return null;
        }

        return $this->reopen($result, __('Publication programmée le :date (UTC).', ['date' => $at->format('d/m/Y à H:i')]));
    }

    /** Repasse l'article en brouillon (depublication, ou annulation d'une programmation). */
    public function unpublish(UnpublishBlogPostAction $unpublishPost): void
    {
        if ($this->postId === null) {
            return;
        }

        try {
            $unpublishPost->execute($this->postId);
        } catch (BaseAppException $e) {
            $this->reportRefusal($e);

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin unpublish blog post');

            return;
        }

        $this->resetErrorBag('form');
        $this->dispatch('ui-toast', title: __('Article repassé en brouillon.'), type: 'success');
    }

    public function delete(DeleteBlogPostAction $deletePost): void
    {
        if ($this->postId === null) {
            return;
        }

        try {
            $deletePost->execute($this->postId);
        } catch (BaseAppException $e) {
            $this->reportRefusal($e);

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin delete blog post');

            return;
        }

        session()->flash('toast-success', __('Article supprimé.'));
        $this->redirectRoute('admin.blog.posts.index');
    }

    /**
     * Adresse proposee pour une langue, a partir du titre saisi. Rien n'est enregistre, rien n'est rendu.
     */
    #[Renderless]
    public function suggestSlug(string $locale, BlogSlugSuggestionService $slugs): string
    {
        if (! array_key_exists($locale, self::LOCALES)) {
            return '';
        }

        try {
            return $slugs->suggest($locale, (string) ($this->texts[$locale]['title'] ?? ''), $this->postId);
        } catch (Throwable $e) {
            // La proposition est un confort : en cas d'echec, le champ reste vide et l'adresse sera
            // calculee a l'enregistrement.
            Log::warning('Blog slug suggestion failed.', ['locale' => $locale, 'exception' => $e]);

            return '';
        }
    }

    public function addSource(): void
    {
        if (count($this->sources) < self::MAX_SOURCES) {
            $this->sources[] = ['label' => '', 'url' => ''];
        }
    }

    public function removeSource(int $index): void
    {
        unset($this->sources[$index]);

        $this->sources = array_values($this->sources);
        $this->resetErrorBag();
    }

    /** Appele par wire:sort : rang de la source deplacee et sa nouvelle place (a partir de 0). */
    public function sortSources(int|string $index, int|string $position): void
    {
        $index = (int) $index;

        if (! isset($this->sources[$index])) {
            return;
        }

        $moved = $this->sources[$index];
        $sources = $this->sources;

        unset($sources[$index]);

        $sources = array_values($sources);
        array_splice($sources, max(0, min((int) $position, count($sources))), 0, [$moved]);

        $this->sources = $sources;
    }

    public function render(BlogPostFormService $form, BlogSlugSuggestionService $slugs): View
    {
        $view = null;
        $categoryOptions = [];

        try {
            $categoryOptions = $form->categoryOptions();

            if ($this->postId !== null) {
                $view = $this->opening?->view ?? $form->current($this->postId);

                if ($view === null) {
                    $this->addError('post-load-failed', __('Cet article n\'existe plus. Il a peut-être été supprimé depuis un autre onglet.'));
                } else {
                    $this->resetErrorBag('post-load-failed');
                }
            }
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin blog post form.', ['post_id' => $this->postId, 'exception' => $e]);
            $this->addError('post-load-failed', __('Le chargement de l\'article a échoué. Réessayez ; si le problème persiste, contactez-nous.'));
        }

        return view('livewire.admin.blog.post-form', [
            'locales' => self::LOCALES,
            'view' => $view,
            'categoryOptions' => $categoryOptions,
            // Remis au navigateur a l'ouverture seulement : ensuite, c'est lui qui detient le contenu.
            'initialContents' => $this->firstRender ? ($this->opening?->contents ?? array_fill_keys(array_keys(self::LOCALES), null)) : null,
            'previewUrlTemplate' => route('admin.blog.posts.preview', ['post' => '__ID__', 'locale' => '__LOCALE__']),
            'addressPrefixes' => $slugs->pathPrefixes('blog.show'),
        ])->title(($this->postId === null ? __('Nouvel article') : __('Modifier l\'article')));
    }

    /**
     * Rassemble l'etat du formulaire et le contenu recu du navigateur. Null (et un message) si le
     * contenu d'une langue n'a pas ete transmis : on refuse d'enregistrer plutot que d'effacer un texte.
     *
     * @param  array<string, mixed>  $contents
     */
    private function data(array $contents): ?SaveBlogPostData
    {
        $translations = [];

        foreach (array_keys(self::LOCALES) as $locale) {
            if (! array_key_exists($locale, $contents)) {
                $this->addError('form', __('Le contenu de l\'article n\'a pas été transmis. Rechargez la page avant d\'enregistrer.'));

                return null;
            }

            $translations[$locale] = new BlogPostTranslationData(
                title: $this->texts[$locale]['title'] ?? null,
                slug: $this->texts[$locale]['slug'] ?? null,
                excerpt: $this->texts[$locale]['excerpt'] ?? null,
                content: is_array($contents[$locale]) ? $contents[$locale] : null,
                metaTitle: $this->texts[$locale]['metaTitle'] ?? null,
                metaDescription: $this->texts[$locale]['metaDescription'] ?? null,
            );
        }

        return new SaveBlogPostData(
            postId: $this->postId,
            translations: $translations,
            categoryId: $this->categoryId === '' ? null : (int) $this->categoryId,
            featuredImageId: $this->featuredImageId,
            shareImageId: $this->shareImageId,
            relatedPostIds: $this->relatedIds,
            sources: array_map(
                static fn (array $source): BlogSourceData => new BlogSourceData((string) ($source['label'] ?? ''), (string) ($source['url'] ?? '')),
                array_values($this->sources),
            ),
        );
    }

    /** Message precis d'un refus metier, affiche en haut du formulaire tant qu'il n'est pas corrige. */
    private function reportRefusal(BaseAppException $e): void
    {
        Log::error($e->getMessage(), ['exception' => $e]);

        $this->addError('form', $e->getUserMessage());
    }

    /** Les adresses calculees a l'enregistrement (generees, nettoyees) reviennent dans les champs. */
    private function adoptSavedSlugs(BlogPost $post): void
    {
        foreach (array_keys(self::LOCALES) as $locale) {
            $slug = (string) $post->translationFor($locale)?->slug;

            $this->texts[$locale]['slug'] = $slug;
            $this->slugEdited[$locale] = $slug !== '';
        }
    }

    /** Apres "publier" ou "programmer" : le formulaire est rouvert sur l'article tel qu'il est enregistre. */
    private function reopen(BlogPostStatusChangeResult $result, string $successMessage): int
    {
        if ($result->changed) {
            session()->flash('toast-success', $successMessage);
        } else {
            session()->flash('toast-warning', __('Article enregistré en brouillon. Il manque encore : :missing.', ['missing' => $result->readiness?->blockerSummary() ?? '']));
        }

        $this->redirectRoute('admin.blog.posts.edit', ['post' => $result->postId]);

        return $result->postId;
    }
}
