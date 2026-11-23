<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\SaveOpenRouterKeyAction;
use App\Exceptions\Blog\OpenRouterKeyException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\OpenRouterKeyStateService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * La section « Clé d'accès OpenRouter » des reglages de la generation (decision de Micha du 23/11 : la cle quitte
 * le .env, la cliente la colle elle-meme). Elle montre le debut et la fin de la cle enregistree et son etat, et
 * enregistre une nouvelle cle apres l'avoir verifiee chez OpenRouter. Le champ ne sert qu'a ecrire : la cle
 * enregistree n'est jamais renvoyee au navigateur. Composant a part, avec son propre bouton, car les reglages
 * s'enregistrent ensemble et la cle seule.
 */
class OpenRouterKeyForm extends Component
{
    use HandlesAdminErrors;

    /** OpenRouter rend des cles d'environ 75 caracteres ; au-dela, ce n'est pas une cle seule. */
    private const MAX_LENGTH = 200;

    public string $newKey = '';

    public function save(SaveOpenRouterKeyAction $saveKey): void
    {
        $this->newKey = trim($this->newKey);
        $this->validate(
            ['newKey' => ['required', 'string', 'max:'.self::MAX_LENGTH]],
            [
                'newKey.required' => __('Collez la nouvelle clé.'),
                'newKey.max' => __('Ce texte est trop long pour une clé. Copiez seulement la clé.'),
            ],
        );

        try {
            $saveKey->execute($this->newKey);
        } catch (OpenRouterKeyException $e) {
            Log::log($e->logLevel(), $e->getMessage(), ['exception' => $e]);
            $this->addError('newKey', $e->getUserMessage());

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin save OpenRouter key');

            return;
        }

        $this->reset('newKey');
        $this->dispatch('ui-toast', title: __('Clé vérifiée et enregistrée.'), type: 'success');
    }

    public function render(OpenRouterKeyStateService $keys): View
    {
        $state = null;

        try {
            $state = $keys->current();
            $this->resetErrorBag('key-load-failed');
        } catch (Throwable $e) {
            Log::error('Unexpected error while loading the OpenRouter key state.', ['exception' => $e]);
            $this->addError('key-load-failed', __('L\'état de la clé n\'a pas pu être lu.'));
        }

        return view('livewire.admin.blog.open-router-key-form', ['state' => $state]);
    }
}
