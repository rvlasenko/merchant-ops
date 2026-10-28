<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('customer_name');
            $table->integer('total_cents');
            $table->char('currency', 3);
            // Enum values are hard-coded so this migration never changes when an enum grows.
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending')->index();
            $table->string('marketplace_order_id', 64)->nullable()->unique();
            $table->enum('last_error_code', ['timeout', 'unavailable', 'rate_limited', 'validation_failed', 'unauthorized'])->nullable();
            $table->integer('attempts_count')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_cents_check CHECK (total_cents >= 0)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_attempts_count_check CHECK (attempts_count >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
