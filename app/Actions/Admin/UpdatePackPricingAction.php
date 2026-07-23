<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Submission\SubmissionType;
use App\Repositories\SettingRepository;
use App\Services\Billing\PackPricingService;
use Illuminate\Support\Facades\DB;

/**
 * Persists admin-set annual prices (in cents) for the editable packs, then drops the pricing cache so
 * the new amounts take effect immediately across the app (payment, proration, display, renewal,
 * contract PDF). Amounts are validated upstream by the Livewire component.
 */
final readonly class UpdatePackPricingAction
{
    public function __construct(
        private SettingRepository $settings,
        private PackPricingService $pricing,
    ) {}

    /** @param  array<string, int>  $centsByTypeValue  keyed by SubmissionType value */
    public function execute(array $centsByTypeValue): void
    {
        DB::transaction(function () use ($centsByTypeValue): void {
            foreach ($centsByTypeValue as $typeValue => $cents) {
                $type = SubmissionType::from($typeValue);
                $this->settings->put($this->pricing->settingKey($type), (string) $cents);
            }
        });

        $this->pricing->forget();
    }
}
