<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('trigger', ['initial', 'retry']);
            $table->enum('result', ['success', 'failed']);
            $table->enum('error_code', ['timeout', 'unavailable', 'rate_limited', 'validation_failed', 'unauthorized'])->nullable();
            // Message and retryable flag are snapshots taken at attempt time.
            $table->text('error_message')->nullable();
            $table->boolean('retryable')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE sync_attempts ADD CONSTRAINT sync_attempts_result_error_check CHECK (
                (result = 'success' AND error_code IS NULL AND error_message IS NULL AND retryable IS NULL)
                OR
                (result = 'failed' AND error_code IS NOT NULL AND error_message IS NOT NULL AND retryable IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_attempts');
    }
};
