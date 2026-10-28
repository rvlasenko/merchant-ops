<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulated_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('error_code', ['timeout', 'unavailable', 'rate_limited', 'validation_failed', 'unauthorized']);
            // null: fail on every attempt; N: fail N more times; 0: exhausted.
            $table->integer('remaining')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE simulated_failures ADD CONSTRAINT simulated_failures_remaining_check CHECK (remaining IS NULL OR remaining >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('simulated_failures');
    }
};
