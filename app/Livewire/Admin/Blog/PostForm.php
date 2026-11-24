<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Admin\Blog\SaveAndPublishBlogPostAction;
use App\Actions\Admin\Blog\SaveAndScheduleBlogPostAction;
use App\Actions\Blog\Category\SaveBlogCategoryAction;
use App\Actions\Blog\Image\RecropBlogImageAction;
use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Actions\Blog\Image\UpdateBlogImageAltAction;
use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Actions\Blog\Post\UnpublishBlogPostAction;
use App\Data\Admin\Blog\BlogPostFormOpening;
use App\Data\Admin\Blog\BlogPostStatusChangeResult;
use App\Data\Blog\BlogCategoryTranslationData;
use App\Data\Blog\BlogImageCrop;
use App\Data\Blog\BlogPostTranslationData;
use App\Data\Blog\BlogSourceData;
use App\Data\Blog\SaveBlogCategoryData;
use App\Data\Blog\SaveBlogPostData;
use App\Data\Blog\StoreBlogImageData;
use App\Enums\Blog\BlogImageUsage;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Livewire\Concerns\HandlesMountFailure;
use App\Models\BlogPost;
use App\Services\Admin\Blog\BlogPostFormService;
use App\Services\Admin\Blog\BlogSlugSuggestionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Back-office : creation et modification d'un article du blog, dans les trois langues.
 *
 * Le composant est un bord : il garde l'etat des champs, valide leur forme, delegue aux Actions de SC2
 * et traduit leur issue. Le CONTENU des trois langues n'est pas dans son etat : il vit dans le
 * navigateur (resources/js/admin/blog-post-form.js) et arrive en argument au moment d'enregistrer, pour
 * que les allers-retours restent legers. Aucune requete ici.
 *
 * Images : le fichier arrive par l'envoi de Livewire, l'Action l'enregistre "en attente" et le navigateur
 * garde son apercu ; l'image n'est rattachee a l'article qu'a l'enregistrement de celui-ci.
 */
#[Layout('layouts.admin')]
class PostForm extends Component
{
    use HandlesAdminErrors;
    use HandlesMountFailure;
    use WithFileUploads;

    private const LOCALES = ['en' => 'anglais', 'fr' => 'français', 'es' => 'espagnol'];

    /** Duree d'affichage d'un refus d'image : le message dit quoi corriger, il se lit en entier. */
    private const IMAGE_REFUSAL_LIFE = 9000;

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

    /**
     * Les doutes de la verification d'un brouillon genere, lus a l'ouverture : ils ne changent plus ensuite.
     *
     * @var list<array{where: string|null, statement: string, check: string}>
     */
    #[Locked]
    public array $attentionPoints = [];

    /**
     * Les demandes d'image ou de traduction faites depuis un article neuf, avant son premier enregistrement : il
     * n'avait pas encore d'identifiant, son enregistrement les lui rattache (Micha, 24/11/2026).
     *
     * @var list<int>
     */
    #[Locked]
    public array $requestsBeforeFirstSave = [];

    /**
     * Fichier d'image en cours d'envoi (image principale, de partage ou du texte). Il ne reste dans
     * l'etat que le temps d'etre enregistre par storeImage().
     *
     * @var TemporaryUploadedFile|null
     */
    public $imageUpload = null;

    /** La liste des articles a proposer comme articles lies est ouverte : elle n'est lue que dans ce cas. */
    public bool $pickingRelated = false;

    /**
     * Les articles lies tels que la fenetre de choix les tient, en cours de choix : l'article ne les recoit qu'a
     * « Terminé » (applyRelated), et les perd si la fenetre se ferme autrement (Micha, 24/11/2026).
     *
     * @var list<int>
     */
    #[Locked]
    public array $relatedDraftIds = [];

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

        try {
            $this->opening = $form->open((int) $post);
        } catch (Throwable $e) {
            $this->failedToLoad($e, 'the admin blog post form', ['post_id' => (int) $post]);

            return;
        }

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
        $this->attentionPoints = $this->opening->attentionPoints;

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

    /**
     * Enregistre l'image qui vient d'etre envoyee. Elle nait sans article : c'est l'enregistrement de
     * l'article qui la rattache. Rend ce que le navigateur garde de l'image, ou null si elle est refusee.
     *
     * Une image du texte arrive de la fenetre d'ajout avec son cadrage et sa description : elle est decoupee
     * par le serveur, et sa source non decoupee est gardee pour un recadrage ulterieur.
     *
     * @param  string  $usage  valeur de BlogImageUsage (featured, share, content)
     * @param  array<string, mixed>|null  $crop  rectangle a garder (x, y, width, height), en pixels de l'image
     * @param  array<string, mixed>  $alt  description par langue, pour les langues deja redigees
     * @return array{id: int, url: ?string, alt: array<string, string>, source: ?string, crop: ?array<string, int>}|null
     */
    public function storeImage(string $usage, StoreBlogImageAction $storeImage, BlogPostFormService $form, ?array $crop = null, array $alt = []): ?array
    {
        $imageUsage = BlogImageUsage::tryFrom($usage);
        $file = $this->imageUpload;

        if ($imageUsage === null || ! $file instanceof TemporaryUploadedFile) {
            $this->reset('imageUpload');
            $this->dispatch('ui-toast', type: 'error', title: __('Aucun fichier n\'a été reçu. Réessayez.'));

            return null;
        }

        try {
            $image = $storeImage->execute(new StoreBlogImageData(
                path: $file->getRealPath(),
                usage: $imageUsage,
                alt: array_map(static fn (mixed $text): string => is_string($text) ? $text : '', $alt),
                nameHint: $this->nameHint(),
                // Seule une image du texte se recadre : l'image principale et l'image de partage gardent leur format.
                crop: $imageUsage === BlogImageUsage::Content ? BlogImageCrop::fromArray($crop) : null,
                keepSource: $imageUsage === BlogImageUsage::Content,
            ));
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', type: 'error', title: $e->getUserMessage(), life: self::IMAGE_REFUSAL_LIFE);

            return null;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin store blog image');

            return null;
        } finally {
            // Le fichier temporaire ne sert plus, que l'image ait ete acceptee ou non.
            $this->discardUpload($file);
        }

        match ($imageUsage) {
            BlogImageUsage::Featured => $this->featuredImageId = $image->id,
            BlogImageUsage::Share => $this->shareImageId = $image->id,
            BlogImageUsage::Content => null,
        };

        return $form->imageSummary($image);
    }

    /**
     * Recadre de nouveau une image du texte. Rend la NOUVELLE image (l'ancienne reste intacte tant que
     * l'article n'est pas enregistre), que le navigateur met a la place de l'ancienne dans le texte, ou
     * null si le recadrage est refuse.
     *
     * @param  array<string, mixed>  $crop  rectangle a garder (x, y, width, height), en pixels de la source gardee
     * @return array{id: int, url: ?string, alt: array<string, string>, source: ?string, crop: ?array<string, int>}|null
     */
    public function recropImage(int $imageId, array $crop, RecropBlogImageAction $recrop, BlogPostFormService $form): ?array
    {
        $rectangle = BlogImageCrop::fromArray($crop);

        if ($rectangle === null) {
            $this->dispatch('ui-toast', type: 'error', title: __('Le cadrage n\'a pas été reçu. Réessayez.'));

            return null;
        }

        try {
            $image = $recrop->execute($imageId, $rectangle, $this->postId);
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', type: 'error', title: $e->getUserMessage(), life: self::IMAGE_REFUSAL_LIFE);

            return null;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin recrop blog image');

            return null;
        }

        return $form->imageSummary($image);
    }

    /** Retire l'image principale de l'article (pris en compte a l'enregistrement). */
    public function removeFeaturedImage(): void
    {
        $this->featuredImageId = null;
    }

    /**
     * L'image principale trouvee ou creee depuis l'article (PostFeaturedImage, 24/11/2026), pour la montrer avant de
     * la poser quand l'article en a deja une. Rien ne change dans le formulaire.
     *
     * @return array{id: int, url: ?string, alt: array<string, string>, source: ?string, crop: ?array<string, int>}|null
     */
    public function foundImage(int $imageId, BlogPostFormService $form): ?array
    {
        try {
            return $form->usableImageSummary($imageId, $this->postId);
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', type: 'error', title: $e->getUserMessage());
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin read found blog image');
        }

        return null;
    }

    /**
     * Pose l'image principale trouvee ou creee depuis l'article, ou reprise dans « Images déjà obtenues ».
     * L'identifiant est verrouille : c'est le serveur qui le pose, apres avoir verifie que l'image est libre ou deja a
     * cet article, et qu'elle n'a pas ete obtenue pour un autre. L'enregistrement de l'article la rattache. Une image
     * obtenue et remplacee reste a l'article ; une image deposee et remplacee part au menage de la nuit.
     *
     * @return array{id: int, url: ?string, alt: array<string, string>, source: ?string, crop: ?array<string, int>}|null
     */
    public function useFoundImage(int $imageId, BlogPostFormService $form): ?array
    {
        $summary = $this->foundImage($imageId, $form);

        if ($summary !== null) {
            $this->featuredImageId = $imageId;
        }

        return $summary;
    }

    /**
     * Retient une demande d'image ou de traduction faite depuis l'article neuf (PostFeaturedImage, PostTranslation),
     * pour la lui rattacher a son premier enregistrement. Sans rendu : rien ne change a l'ecran.
     */
    #[On('blog-request-queued')]
    #[Renderless]
    public function rememberRequest(int $generationId): void
    {
        if ($this->postId === null && ! in_array($generationId, $this->requestsBeforeFirstSave, true)) {
            $this->requestsBeforeFirstSave[] = $generationId;
        }
    }

    /** Retire l'image de partage : les reseaux sociaux reprendront l'image principale. */
    public function removeShareImage(): void
    {
        $this->shareImageId = null;
    }

    /**
     * Enregistre tout de suite les descriptions d'une image de l'article, dans les trois langues.
     *
     * @param  array<string, mixed>  $alt  description par langue
     * @return array<string, string>|null descriptions enregistrees pour chaque langue, ou null en cas d'echec
     */
    public function saveImageAlt(int $imageId, array $alt, UpdateBlogImageAltAction $updateAlt): ?array
    {
        try {
            $saved = $updateAlt->execute($imageId, $alt, $this->postId);
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', type: 'error', title: $e->getUserMessage());

            return null;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin update blog image descriptions');

            return null;
        }

        $this->dispatch('ui-toast', type: 'success', title: __('Descriptions de l\'image enregistrées.'));

        $descriptions = [];

        foreach (array_keys(self::LOCALES) as $locale) {
            $descriptions[$locale] = (string) ($saved[$locale] ?? '');
        }

        return $descriptions;
    }

    /** Ouvre la liste des articles a proposer comme articles lies, sur une copie des articles lies de l'article. */
    public function openRelatedPicker(): void
    {
        $this->pickingRelated = true;
        $this->relatedDraftIds = $this->relatedIds;
    }

    /** La fenetre se ferme sans « Terminé » : ce qui y a ete choisi est abandonne. */
    public function closeRelatedPicker(): void
    {
        $this->pickingRelated = false;
        $this->relatedDraftIds = [];
    }

    /**
     * « Terminé » : l'article recoit les articles lies choisis dans la fenetre (pris en compte a l'enregistrement).
     *
     * @return bool vrai si la liste a change, pour que le formulaire se sache modifie
     */
    public function applyRelated(): bool
    {
        $changed = $this->pickingRelated && $this->relatedDraftIds !== $this->relatedIds;

        if ($this->pickingRelated) {
            $this->relatedIds = $this->relatedDraftIds;
        }

        $this->closeRelatedPicker();

        return $changed;
    }

    /**
     * Ajoute un article lie a la suite des autres, dans la fenetre de choix seulement. Elle reste ouverte : on peut
     * en ajouter plusieurs de suite, et l'article ne les recoit qu'a « Terminé ».
     */
    public function addRelated(int|string $postId): void
    {
        $postId = (int) $postId;

        if (! $this->pickingRelated || $postId <= 0 || $postId === $this->postId || in_array($postId, $this->relatedDraftIds, true)) {
            return;
        }

        if (count($this->relatedDraftIds) >= SaveBlogPostAction::MAX_RELATED_POSTS) {
            return;
        }

        $this->relatedDraftIds[] = $postId;
    }

    /**
     * Retire un article lie. Dans la fenetre de choix, de la seule selection en cours ; dans la section de la page
     * (croix d'une ligne), de l'article directement.
     */
    public function removeRelated(int|string $postId): void
    {
        $postId = (int) $postId;
        $without = static fn (array $ids): array => array_values(array_filter($ids, static fn (int $id): bool => $id !== $postId));

        if ($this->pickingRelated) {
            $this->relatedDraftIds = $without($this->relatedDraftIds);

            return;
        }

        $this->relatedIds = $without($this->relatedIds);
    }

    /** Appele par wire:sort : identifiant de l'article lie deplace et sa nouvelle place (a partir de 0). */
    public function reorderRelated(int|string $postId, int|string $position): void
    {
        $postId = (int) $postId;

        if (! in_array($postId, $this->relatedIds, true)) {
            return;
        }

        $others = array_values(array_filter($this->relatedIds, static fn (int $id): bool => $id !== $postId));
        array_splice($others, max(0, min((int) $position, count($others))), 0, [$postId]);

        $this->relatedIds = $others;
    }

    /**
     * Ajoute une source, ou remplace celle du rang donne, apres controle : la fenetre de saisie vit dans
     * le navigateur, qui affiche les refus rendus ici. Une source refusee n'entre jamais dans la liste.
     *
     * @return array<string, string> refus par champ (`label`, `url`, `form`), vide si la source est entree
     */
    public function saveSource(?int $index, string $label, string $url): array
    {
        $source = ['label' => trim($label), 'url' => trim($url)];

        $validator = Validator::make($source, [
            'label' => ['required', 'string', 'max:200'],
            'url' => ['required', 'string', 'max:2000', 'regex:#^https?://\S+$#i'],
        ], [
            'label.required' => __('L\'intitulé de la source est obligatoire.'),
            'label.max' => __('L\'intitulé de la source ne peut pas dépasser 200 caractères.'),
            'url.required' => __('Le lien de la source est obligatoire.'),
            'url.max' => __('Le lien de la source est trop long.'),
            'url.regex' => __('Le lien de la source doit commencer par http:// ou https://.'),
        ]);

        if ($validator->fails()) {
            return array_map(static fn (array $messages): string => $messages[0], $validator->errors()->toArray());
        }

        if ($index !== null && isset($this->sources[$index])) {
            $this->sources[$index] = $source;
        } elseif (count($this->sources) < self::MAX_SOURCES) {
            $this->sources[] = $source;
        } else {
            return ['form' => __('Un article ne peut pas avoir plus de :max sources.', ['max' => self::MAX_SOURCES])];
        }

        $this->resetErrorBag();

        return [];
    }

    public function removeSource(int $index): void
    {
        unset($this->sources[$index]);

        $this->sources = array_values($this->sources);
        $this->resetErrorBag();
    }

    /**
     * Cree une categorie depuis l'article, sans le quitter (decision de Micha du 19/11), puis la choisit pour lui.
     * La fenetre de saisie vit dans le navigateur, qui affiche les refus rendus ici. Seul le nom est demande,
     * dans chaque langue : l'adresse s'en deduit, la description se complete plus tard depuis Categories. La
     * categorie existe aussitot ; le choix de l'article s'enregistre avec lui.
     *
     * @param  array<string, mixed>  $names  nom par langue
     * @return array<string, string> refus par langue (`en`, `fr`, `es`) ou `form`, vide si la categorie est creee
     */
    public function createCategory(array $names, SaveBlogCategoryAction $saveCategory): array
    {
        $typed = [];
        $rules = [];
        $messages = [];

        foreach (self::LOCALES as $locale => $language) {
            $typed[$locale] = is_string($names[$locale] ?? null) ? trim($names[$locale]) : '';
            $rules[$locale] = ['required', 'string', 'max:255'];
            $messages["{$locale}.required"] = __('Le nom en :language est obligatoire.', ['language' => $language]);
            $messages["{$locale}.max"] = __('Le nom en :language ne peut pas dépasser 255 caractères.', ['language' => $language]);
        }

        $validator = Validator::make($typed, $rules, $messages);

        if ($validator->fails()) {
            return array_map(static fn (array $refusals): string => $refusals[0], $validator->errors()->toArray());
        }

        $translations = array_map(static fn (string $name): BlogCategoryTranslationData => new BlogCategoryTranslationData(name: $name), $typed);

        try {
            $category = $saveCategory->execute(new SaveBlogCategoryData(null, $translations));
        } catch (BaseAppException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);

            return ['form' => $e->getUserMessage()];
        } catch (Throwable $e) {
            Log::error('Unexpected error while creating a blog category from the post form.', ['post_id' => $this->postId, 'exception' => $e]);

            return ['form' => __('La catégorie n\'a pas été créée. Réessayez. Si le problème persiste, contactez l\'assistance technique.')];
        }

        $this->categoryId = (string) $category->id;
        $this->resetErrorBag('categoryId');
        $this->dispatch('ui-toast', title: __('Catégorie créée.'), type: 'success');

        return [];
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
        if ($this->loadFailed) {
            return view('livewire.admin.blog.post-form.load-failed')->title(__("Modifier l'article"))->layoutData(['surface' => 'plain']);
        }

        $view = null;
        $categoryOptions = [];
        $related = [];
        $relatedCandidates = [];
        $relatedChosen = [];

        try {
            $categoryOptions = $form->categoryOptions();

            if ($this->pickingRelated) {
                $relatedCandidates = $form->relatedCandidates(array_values(array_filter([...$this->relatedDraftIds, $this->postId])));
            }

            if ($this->postId === null) {
                // Article pas encore cree : seuls les articles lies deja choisis demandent une lecture.
                $related = $form->related($this->relatedIds);
            } else {
                $view = $this->opening?->view ?? $form->current($this->postId, $this->relatedIds);
                $related = $view->related ?? [];

                if ($view === null) {
                    $this->addError('post-load-failed', __('Cet article n\'existe plus. Il a peut-être été supprimé depuis un autre onglet.'));
                } else {
                    $this->resetErrorBag('post-load-failed');
                }
            }

            // La selection en cours dans la fenetre : lue seulement quand elle differe de celle de l'article.
            if ($this->pickingRelated) {
                $relatedChosen = $this->relatedDraftIds === $this->relatedIds ? $related : $form->related($this->relatedDraftIds);
            }
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the admin blog post form.', ['post_id' => $this->postId, 'exception' => $e]);
            $this->addError('post-load-failed', __('Le chargement de l\'article a échoué. Réessayez. Si le problème persiste, contactez l\'assistance technique.'));
        }

        return view('livewire.admin.blog.post-form', [
            'locales' => self::LOCALES,
            'view' => $view,
            'categoryOptions' => $categoryOptions,
            // Remis au navigateur a l'ouverture seulement : ensuite, c'est lui qui detient le contenu.
            'initialContents' => $this->firstRender ? ($this->opening?->contents ?? array_fill_keys(array_keys(self::LOCALES), null)) : null,
            // Images de l'article (apercu et descriptions), remises une seule fois elles aussi : ensuite le
            // navigateur tient la liste a jour au fil des envois.
            'initialImages' => $this->firstRender ? ($this->opening?->view->images ?? []) + array_column(array_column($this->opening?->obtainedImages ?? [], 'image'), null, 'id') : null,
            // Les images deja obtenues pour l'article (24/11/2026), dans l'ordre, sans leur apercu deja remis ci-dessus.
            'initialObtainedImages' => $this->firstRender
                ? array_map(static fn (array $obtained): array => ['id' => $obtained['image']['id'], 'origin' => $obtained['origin'], 'date' => $obtained['date']], $this->opening?->obtainedImages ?? [])
                : null,
            'related' => $related,
            'relatedCandidates' => $relatedCandidates,
            'relatedChosen' => $relatedChosen,
            'relatedLimit' => SaveBlogPostAction::MAX_RELATED_POSTS,
            'sourceLimit' => self::MAX_SOURCES,
            'relatedCandidateLimit' => BlogPostFormService::RELATED_CANDIDATES,
            'previewUrlTemplate' => route('admin.blog.posts.preview', ['post' => '__ID__', 'locale' => '__LOCALE__']),
            'addressPrefixes' => $slugs->pathPrefixes('blog.show'),
        ])->title(($this->postId === null ? __('Nouvel article') : __('Modifier l\'article')))->layoutData(['surface' => 'plain']);
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
            requestIds: $this->postId === null ? $this->requestsBeforeFirstSave : [],
        );
    }

    /** Texte dont tirer le nom des fichiers d'une image : le premier titre saisi. */
    private function nameHint(): ?string
    {
        foreach (array_keys(self::LOCALES) as $locale) {
            $title = trim((string) ($this->texts[$locale]['title'] ?? ''));

            if ($title !== '') {
                return $title;
            }
        }

        return null;
    }

    /** Supprime le fichier temporaire d'un envoi et vide la propriete : un echec ici ne gene pas l'ecran. */
    private function discardUpload(TemporaryUploadedFile $file): void
    {
        try {
            $file->delete();
        } catch (Throwable $e) {
            Log::warning('Temporary blog image upload could not be deleted.', ['exception' => $e]);
        }

        $this->reset('imageUpload');
    }

    /** Message precis d'un refus metier, affiche en haut du formulaire tant qu'il n'est pas corrige. */
    private function reportRefusal(BaseAppException $e): void
    {
        Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);

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
