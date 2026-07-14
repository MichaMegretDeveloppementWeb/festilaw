<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // App\Enums\Payment\PaymentType
            $table->unsignedInteger('amount_cents'); // centimes, jamais de float
            $table->string('currency', 3)->default('EUR');
            // Provider de paiement (multi-providers possibles : stripe, paypal...) + reference cote provider.
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('status'); // App\Enums\Payment\PaymentStatus
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status', 'payments_status_idx');
            // Lookup du webhook de paiement : WHERE provider = ? AND provider_reference = ?
            $table->index(['provider', 'provider_reference'], 'payments_provider_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
