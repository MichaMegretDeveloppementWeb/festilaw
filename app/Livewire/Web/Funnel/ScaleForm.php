<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Scale\CreateScaleSubmissionAction;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HasSpamProtection;
use App\Livewire\Web\Funnel\Concerns\HasFunnelContactFields;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ScaleForm extends Component
{
    use HasFunnelContactFields;
    use HasSpamProtection;

    public bool $sent = false;

    public function submit(CreateScaleSubmissionAction $action): void
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
            $action->execute($this->funnelData());
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('form', $e->getUserMessage());

            return;
        }

        $this->resetContactFields();
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.web.funnel.scale-form');
    }
}
