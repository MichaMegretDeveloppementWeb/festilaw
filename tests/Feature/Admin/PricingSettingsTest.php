<?php

use App\Enums\Submission\SubmissionType;
use App\Livewire\Admin\PricingSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\PackPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('festilaw.starter.amount_cents', 33300);
    config()->set('festilaw.pro.amount_cents', 120000);
});

it('requires authentication to reach the pricing settings', function () {
    get(route('admin.settings'))->assertRedirect(route('admin.login'));
});

it('falls back to the config default when no override is set', function () {
    expect(app(PackPricingService::class)->annualCents(SubmissionType::Starter))->toBe(33300)
        ->and(SubmissionType::Pro->annualCents())->toBe(120000);
});

it('prefills the form with the current effective prices', function () {
    actingAs(User::factory()->create());

    Livewire::test(PricingSettings::class)
        ->assertSet('creatorPrice', '333.00')
        ->assertSet('proPrice', '1200.00');
});

it('lets an admin change the pack prices, applied everywhere at once', function () {
    actingAs(User::factory()->create());

    Livewire::test(PricingSettings::class)
        ->set('creatorPrice', '1')       // tarif de test a 1 EUR
        ->set('proPrice', '2.50')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('admin-toast');

    // Persiste en centimes...
    expect(Setting::where('key', 'pricing.starter_annual_cents')->value('value'))->toBe('100')
        ->and(Setting::where('key', 'pricing.pro_annual_cents')->value('value'))->toBe('250');

    // ... et l'override prime partout via SubmissionType::annualCents() (paiement, prorata, contrat, renouvellement).
    app(PackPricingService::class)->forget();
    expect(SubmissionType::Starter->annualCents())->toBe(100)
        ->and(SubmissionType::Pro->annualCents())->toBe(250);
});

it('rejects a price below 1 euro', function () {
    actingAs(User::factory()->create());

    Livewire::test(PricingSettings::class)
        ->set('creatorPrice', '0')
        ->call('save')
        ->assertHasErrors(['creatorPrice']);

    expect(Setting::where('key', 'pricing.starter_annual_cents')->exists())->toBeFalse();
});

it('reverts to the real price by saving it again', function () {
    actingAs(User::factory()->create());
    Setting::create(['key' => 'pricing.starter_annual_cents', 'value' => '100']); // etait a 1 EUR
    app(PackPricingService::class)->forget();

    Livewire::test(PricingSettings::class)
        ->set('creatorPrice', '333')
        ->call('save')
        ->assertHasNoErrors();

    app(PackPricingService::class)->forget();
    expect(SubmissionType::Starter->annualCents())->toBe(33300);
});
