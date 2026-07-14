<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Pro\CreateProSubmissionAction;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Livewire\Concerns\HasSpamProtection;
use App\Livewire\Web\Funnel\Concerns\HasFunnelContactFields;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class ProForm extends Component
{
    use HandlesUnexpectedErrors;
    use HasFunnelContactFields;
    use HasSpamProtection;

    public bool $sent = false;

    public function submit(CreateProSubmissionAction $action): void
    {
        if ($this->looksLikeSpam()) {
            $this->sent = true; // on fait comme si, sans rien creer

            return;
        }

        if ($this->tooManyAttempts('funnel-pro')) {
            return;
        }

        $this->validate();

        try {
            $action->execute($this->funnelData());
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('form', $e->getUserMessage());

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'form', 'PRO enquiry submit');

            return;
        }

        // WhatsApp fourni par la cliente (LV2). En attendant, on affiche un etat de succes.
        $whatsapp = config('festilaw.pro.whatsapp_url');
        if (is_string($whatsapp) && $whatsapp !== '') {
            $this->redirect($whatsapp);

            return;
        }

        $this->resetContactFields();
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.web.funnel.pro-form');
    }
}
