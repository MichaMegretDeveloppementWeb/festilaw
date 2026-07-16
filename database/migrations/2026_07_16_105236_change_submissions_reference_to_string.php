<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `reference` etait typee `uuid` (char(36)) mais stocke un code lisible "FL-XXXX-XXXX" : on aligne le
 * type sur la realite (string). L'index unique existant est conserve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('reference')->change();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->uuid('reference')->change();
        });
    }
};
