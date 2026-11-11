<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// An order has an external ID exactly when it is synced (task 1.3, D3).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE orders ADD CONSTRAINT orders_external_order_id_check
              CHECK ((sync_status = 'synced') = (external_order_id IS NOT NULL))
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_external_order_id_check');
    }
};
