<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Submission\SubmissionType;
use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Effective annual price (in cents) for a subscription pack : an admin override stored in settings if
 * present, otherwise the config default. This is the single resolution point behind
 * SubmissionType::annualCents(), so every consumer (payment, proration, journey display, renewal,
 * contract PDF) reflects an admin change with no code touch. Overrides are cached (invalidated on
 * update) and memoized per request ; the service is a singleton so repeated lookups are free.
 */
final class PackPricingService
{
    private const CACHE_KEY = 'festilaw.pack_pricing';

    /** Packs whose annual price is admin-editable. */
    public const EDITABLE = [SubmissionType::Starter, SubmissionType::Pro];

    /** @var array<string, int>|null Memoized admin overrides (type value => cents). */
    private ?array $memo = null;

    public function __construct(private readonly SettingRepository $settings) {}

    public function annualCents(SubmissionType $type): int
    {
        return $this->overrides()[$type->value] ?? $this->defaultCents($type);
    }

    public function settingKey(SubmissionType $type): string
    {
        return 'pricing.'.$type->value.'_annual_cents';
    }

    /** Config default (used when no admin override is set), exposed for the admin UI hint. */
    public function defaultCents(SubmissionType $type): int
    {
        return match ($type) {
            SubmissionType::Starter => (int) config('festilaw.starter.amount_cents', 33300),
            SubmissionType::Pro => (int) config('festilaw.pro.amount_cents', 120000),
            default => throw new LogicException("No annual fee for submission type {$type->value}."),
        };
    }

    /** Drop the cache + per-request memo so a new price takes effect immediately. */
    public function forget(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, int> Admin overrides by type value (positive amounts only). */
    private function overrides(): array
    {
        return $this->memo ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            $map = [];
            foreach (self::EDITABLE as $type) {
                $raw = $this->settings->get($this->settingKey($type));
                if ($raw !== null && (int) $raw > 0) {
                    $map[$type->value] = (int) $raw;
                }
            }

            return $map;
        });
    }
}
