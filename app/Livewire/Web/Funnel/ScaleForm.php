<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Scale\CreateScaleSubmissionAction;
use App\Contracts\Analytics\AudienceTracker;
use App\Enums\Analytics\AudienceEvent;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Livewire\Concerns\HasSpamProtection;
use App\Livewire\Web\Funnel\Concerns\HasFunnelContactFields;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class ScaleForm extends Component
{
    use HandlesUnexpectedErrors;
    use HasFunnelContactFields;
    use HasSpamProtection;

    public bool $sent = false;

    public function submit(CreateScaleSubmissionAction $action, AudienceTracker $audience): void
    {
        if ($this->looksLikeSpam()) {
            $this->sent = true;

            return;
        }

        if ($this->tooManyAttempts('funnel-scale')) {
            return;
        }

        $this->validate();

        try {
            $submission = $action->execute($this->funnelData());
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('form', __($e->getUserMessage()));

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'form', 'SCALE audit request submit');

            return;
        }

        $audience->record(AudienceEvent::ScaleRequested);
        $this->resetContactFields();

        // On enchaine directement sur l'espace SCALE (payer l'audit puis reserver) ; le token vaut acces.
        // Le lien est aussi envoye par email pour revenir plus tard. Une page complete, et non une navigation
        // Livewire : jusqu'a Falcon Analytics 599edd0 (20/11), le collecteur ne comptait pas une page ouverte
        // par navigation. Il la compte depuis, et la page complete reste sans gene.
        $this->redirectRoute('get-started.scale.space', [
            'dossier' => $submission->resume_token,
        ]);
    }

    public function render()
    {
        return view('livewire.web.funnel.scale-form');
    }
}
