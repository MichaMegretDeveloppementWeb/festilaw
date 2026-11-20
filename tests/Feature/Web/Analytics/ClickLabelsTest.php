<?php

use App\Actions\Web\Starter\CreateStarterSubmissionAction;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\BlogContent;

use function Pest\Laravel\get;

/*
 | Mesure d'audience (cartographie du 20/11) : le collecteur de Falcon Analytics enregistre tout element
 | cliquable, et sans nom ni libelle fixe il le range sous son texte affiche, qui change avec la langue, porte un
 | titre d'article ou reste vide. Chaque cliquable du site public porte donc un nom (`data-track-event`) ou un
 | libelle fixe (`data-track-label`), sur lui ou sur un parent, ou en est exclu (`data-track-ignore`).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    config()->set('festilaw.starter.required_documents', ['turnover_proof', 'technical_documentation']);
});

/**
 * Les cliquables d'une page sans nom ni libelle, selon les regles du collecteur : controles natifs, roles ARIA
 * cliquables et elements que Livewire ou Alpine rendent cliquables.
 *
 * @return list<string>
 */
function clickablesWithoutLabel(string $html): array
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
    libxml_clear_errors();

    $roles = ['button', 'link', 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'tab', 'option', 'switch'];
    $missing = [];

    foreach ((new DOMXPath($dom))->query('//body//*') as $element) {
        $tag = strtolower($element->nodeName);
        $interactive = in_array($tag, ['a', 'button', 'summary'], true)
            || ($tag === 'input' && in_array(strtolower($element->getAttribute('type')), ['submit', 'button', 'reset', 'image', 'checkbox', 'radio'], true))
            || in_array($element->getAttribute('role'), $roles, true);

        foreach ($element->attributes as $attribute) {
            $interactive = $interactive || preg_match('/^(wire:click|@click|x-on:click|onclick)/', $attribute->nodeName) === 1;
        }

        if (! $interactive) {
            continue;
        }

        for ($node = $element, $labelled = false; $node instanceof DOMElement && ! $labelled; $node = $node->parentNode) {
            $labelled = $node->hasAttribute('data-track-event') || $node->hasAttribute('data-track-label') || $node->hasAttribute('data-track-ignore');
        }

        if (! $labelled) {
            $missing[] = $tag.' « '.Str::limit(trim((string) preg_replace('/\s+/', ' ', $element->textContent)), 50).' »';
        }
    }

    return array_values(array_unique($missing));
}

it('gives every clickable of the public site a name or a fixed label, in every language', function (string $path) {
    $guide = BlogContent::category('guide');
    BlogContent::seedPublished(13, $guide);
    BlogContent::published('labels', $guide);

    $path = str_replace('{dossier}', 'labelstoken', $path);
    Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid, 'resume_token' => 'labelstoken', 'resume_expires_at' => null]);

    expect(clickablesWithoutLabel(get($path)->assertOk()->getContent()))->toBe([]);
})->with([
    '/', '/fr', '/es', '/about', '/pricing', '/fr/pricing', '/understand-gpsr', '/es/understand-gpsr', '/services',
    '/excluded-products', '/contact', '/blog', '/fr/blog', '/blog?page=2', '/blog/labels-en', '/fr/blog/labels-fr',
    '/blog/category/guide-en', '/get-started', '/get-started/starter', '/get-started/pro', '/get-started/scale',
    '/my-project', '/my-project/{dossier}', '/link-expired', '/legal-notice', '/fr/legal-notice', '/privacy-policy',
    '/es/privacy-policy', '/terms',
]);

it('gives every clickable of a new dossier a name or a fixed label, from its first step', function () {
    $submission = app(CreateStarterSubmissionAction::class)->execute([
        'company_name' => 'Wildthread Ceramics', 'first_name' => 'Maya', 'email' => 'maya@example.com',
    ])->submission;

    $html = get(route('get-started.starter.journey', ['dossier' => $submission->resume_token]))->assertOk()->getContent();

    expect(clickablesWithoutLabel($html))->toBe([])
        ->and($html)->toContain("data-track-label=\"Dépôt d'un document\"");
});
