<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Scale\RecordAppointmentAction;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records the SCALE consultation booking (POST) once the client has picked a slot in the provided Google
 * Calendar. There is no calendar webhook (out of scope): this marks the appointment as requested and
 * Festilaw fills the exact slot from the back-office. Guards (in the Action) that the audit is paid first.
 */
final class ScaleBookingController extends Controller
{
    public function __construct(private readonly RecordAppointmentAction $recordAppointment) {}

    public function __invoke(Submission $dossier): RedirectResponse
    {
        abort_unless($dossier->type === SubmissionType::Scale, 404);

        try {
            $this->recordAppointment->execute($dossier);
        } catch (BaseAppException $e) {
            Log::channel('payments')->warning($e->getMessage(), ['exception' => $e, 'submission' => $dossier->id]);

            return $this->backToSpace($dossier, __($e->getUserMessage()));
        } catch (Throwable $e) {
            Log::channel('payments')->error($e->getMessage(), ['exception' => $e, 'submission' => $dossier->id]);

            return $this->backToSpace($dossier, __('Something went wrong on our end. Please try again. If the problem persists, contact us.'));
        }

        return redirect()
            ->route('get-started.scale.space', ['dossier' => $dossier->resume_token])
            ->with('scale_booked', true);
    }

    private function backToSpace(Submission $dossier, string $message): RedirectResponse
    {
        return redirect()
            ->route('get-started.scale.space', ['dossier' => $dossier->resume_token])
            ->with('scale_error', $message);
    }
}
