<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['wb_orders', 'wb_sales', 'wb_stocks', 'wb_incomes'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->bigInteger('nm_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['wb_orders', 'wb_sales', 'wb_stocks', 'wb_incomes'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('nm_id')->nullable()->change();
            });
        }
    }
};
