<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The receiving side was renamed from "marketplace" to a neutral "external service".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('marketplace_order_id', 'external_order_id');
            $table->renameIndex('orders_marketplace_order_id_unique', 'orders_external_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('external_order_id', 'marketplace_order_id');
            $table->renameIndex('orders_external_order_id_unique', 'orders_marketplace_order_id_unique');
        });
    }
};
